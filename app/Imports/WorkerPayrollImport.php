<?php

namespace App\Imports;

use App\Models\PositionCategory;
use App\Models\ProjectWork;
use App\Models\Worker;
use App\Models\WorkerGroup;
use App\Services\WorkerGroupMembershipService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class WorkerPayrollImport implements ToCollection
{
    private WorkerGroupMembershipService $membershipService;

    private array $summary = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'warnings' => 0,
    ];

    public function __construct(?WorkerGroupMembershipService $membershipService = null)
    {
        $this->membershipService = $membershipService ?? app(WorkerGroupMembershipService::class);
    }

    public function collection(Collection $rows): void
    {
        $headers = null;

        foreach ($rows as $row) {
            $values = array_values($row->all());
            $detectedHeaders = $this->detectHeaders($values);

            if ($detectedHeaders) {
                $headers = $detectedHeaders;

                continue;
            }

            if (! $headers) {
                continue;
            }

            $employeeCode = $this->value($values, $headers['employee_code']);
            $nss = $this->value($values, $headers['nss']);
            $firstName = $this->value($values, $headers['first_name']);
            $lastName = trim(implode(' ', array_filter([
                $this->value($values, $headers['last_name_paternal']),
                $this->value($values, $headers['last_name_maternal']),
            ])));

            if (! $firstName && ! $lastName && ! $employeeCode) {
                continue;
            }

            if (! preg_match('/^\d+$/', $employeeCode)) {
                $this->summary['skipped']++;
                $this->summary['warnings']++;

                continue;
            }

            $salary = $this->decimal($this->value($values, $headers['weekly_salary']));

            if ($salary === null) {
                $this->summary['skipped']++;
                $this->summary['warnings']++;

                continue;
            }

            if (! $firstName || ! $lastName) {
                $this->summary['skipped']++;
                $this->summary['warnings']++;

                continue;
            }

            $hireDate = $this->date($this->value($values, $headers['hire_date']));
            if ($this->value($values, $headers['hire_date']) !== '' && $hireDate === null) {
                $this->summary['warnings']++;
            }

            $worker = $this->findWorker($nss, $employeeCode);
            $isNew = ! $worker;
            $worker ??= new Worker(['employee_code' => $employeeCode]);

            if ($worker->trashed()) {
                $worker->restore();
            }

            $worker->fill([
                'employee_code' => $employeeCode,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'position_category_id' => $this->positionCategoryId($this->value($values, $headers['position_category'])),
                'weekly_salary' => $salary,
                'nss' => $nss ?: null,
                'curp' => $this->value($values, $headers['curp']) ?: null,
                'hire_date' => $hireDate,
            ]);

            if ($isNew) {
                $worker->status = 'pre_registered';
            }

            $worker->save();

            $this->assignGroup(
                $worker,
                $this->value($values, $headers['place']),
                $this->value($values, $headers['group']),
                $hireDate,
            );

            $this->summary[$isNew ? 'created' : 'updated']++;
        }
    }

    public function summary(): array
    {
        return $this->summary;
    }

    private function detectHeaders(array $values): ?array
    {
        $headers = array_map(fn ($value) => $this->normalize($value), $values);
        $employeeCode = $this->findIndex($headers, fn ($header) => str_contains($header, 'CUENTA'));
        $firstName = $this->findIndex($headers, fn ($header) => str_starts_with($header, 'NOMBRE'));
        $lastNamePaternal = $this->findIndex($headers, fn ($header) => str_contains($header, 'PATERNO'));
        $lastNameMaternal = $this->findIndex($headers, fn ($header) => str_contains($header, 'MATERNO'));
        $positionCategory = $this->findIndex($headers, fn ($header) => $header === 'CATEGORIA');
        $weeklySalary = $this->findIndex($headers, fn ($header) => $header === 'SUELDO');
        $nss = $this->findIndex($headers, fn ($header) => str_contains($header, 'SEGURO SOCIAL'));
        $curp = $this->findIndex($headers, fn ($header) => $header === 'CURP');
        $place = $this->findIndex($headers, fn ($header) => $header === 'LUGAR');
        $group = $this->findIndex($headers, fn ($header) => $header === 'CUADRILLA');
        $hireDate = $this->findIndex($headers, fn ($header) => str_contains($header, 'FECHA DE INGRESO'));

        if ($employeeCode === null || $firstName === null || $lastNamePaternal === null || $weeklySalary === null) {
            return null;
        }

        return compact(
            'employeeCode',
            'firstName',
            'lastNamePaternal',
            'lastNameMaternal',
            'positionCategory',
            'weeklySalary',
            'nss',
            'curp',
            'place',
            'group',
            'hireDate',
        ) + [
            'employee_code' => $employeeCode,
            'first_name' => $firstName,
            'last_name_paternal' => $lastNamePaternal,
            'last_name_maternal' => $lastNameMaternal,
            'position_category' => $positionCategory,
            'weekly_salary' => $weeklySalary,
            'nss' => $nss,
            'curp' => $curp,
            'place' => $place,
            'group' => $group,
            'hire_date' => $hireDate,
        ];
    }

    private function findIndex(array $headers, callable $matches): ?int
    {
        foreach ($headers as $index => $header) {
            if ($matches($header)) {
                return $index;
            }
        }

        return null;
    }

    private function value(array $values, ?int $index): string
    {
        return trim((string) ($index === null ? '' : $values[$index] ?? ''));
    }

    private function decimal(string $value): ?float
    {
        $normalized = str_replace(',', '', $value);
        $normalized = preg_replace('/[^\d.-]/', '', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function findWorker(string $nss, string $employeeCode): ?Worker
    {
        if ($nss !== '') {
            $worker = Worker::withTrashed()->where('nss', $nss)->first();

            if ($worker) {
                return $worker;
            }
        }

        return Worker::withTrashed()->where('employee_code', $employeeCode)->first();
    }

    private function assignGroup(Worker $worker, string $place, string $groupName, ?string $hireDate): void
    {
        if ($place === '' || $groupName === '') {
            return;
        }

        $projectWork = ProjectWork::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($place)])
            ->first();

        if (! $projectWork) {
            $this->summary['warnings']++;

            return;
        }

        $workerGroup = WorkerGroup::firstOrCreate(
            ['name' => $groupName, 'project_work_id' => $projectWork->id],
            ['status' => 'active'],
        );
        $currentGroup = $worker->currentGroup();

        if ($currentGroup?->is($workerGroup)) {
            return;
        }

        $membershipDate = $hireDate ?? now()->toDateString();

        if ($currentGroup) {
            $currentJoinedAt = $currentGroup->pivot->joined_at?->toDateString();
            $membershipDate = max($membershipDate, $currentJoinedAt ?? $membershipDate);
            $this->membershipService->remove($currentGroup, $worker, $membershipDate);
        }

        $this->membershipService->add($workerGroup, $worker, $membershipDate);
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            return Carbon::createFromFormat('!n/j/Y', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function positionCategoryId(string $name): ?int
    {
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';

        if ($name === '') {
            return null;
        }

        return PositionCategory::firstOrCreate(['name' => $name], ['active' => true])->id;
    }

    private function normalize(mixed $value): string
    {
        $value = Str::upper(Str::ascii(trim((string) $value)));
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);

        return trim($value);
    }
}
