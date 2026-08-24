<?php

namespace App\Imports;

use App\Models\Worker;
use App\Models\PositionCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

class WorkerPayrollImport implements ToCollection
{
    private array $summary = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'warnings' => 0,
    ];

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

            $name = $this->value($values, $headers['name']);
            $employeeCode = $this->value($values, $headers['employee_code']);

            if (! $name && ! $employeeCode) {
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

            [$firstName, $lastName] = $this->splitName($name);

            if (! $firstName || ! $lastName) {
                $this->summary['skipped']++;
                $this->summary['warnings']++;
                continue;
            }

            $worker = Worker::withTrashed()->where('employee_code', $employeeCode)->first();
            $isNew = ! $worker;
            $worker ??= new Worker(['employee_code' => $employeeCode]);

            if ($worker->trashed()) {
                $worker->restore();
            }

            $worker->fill([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'position_category_id' => $this->positionCategoryId($this->value($values, $headers['position_category'])),
                'weekly_salary' => $salary,
            ]);

            if ($isNew) {
                $worker->status = 'pre_registered';
            }

            $worker->save();

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
        $name = $this->findIndex($headers, fn ($header) => str_starts_with($header, 'NOMBRE'));
        $positionCategory = $this->findIndex($headers, fn ($header) => $header === 'CATEGORIA');
        $weeklySalary = $this->findIndex($headers, fn ($header) => $header === 'SUELDO');

        if ($employeeCode === null || $name === null || $weeklySalary === null) {
            return null;
        }

        return compact('employeeCode', 'name', 'positionCategory', 'weeklySalary') + [
            'employee_code' => $employeeCode,
            'position_category' => $positionCategory,
            'weekly_salary' => $weeklySalary,
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

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) < 2) {
            return ['', ''];
        }

        $lastName = count($parts) > 2 ? implode(' ', array_splice($parts, -2)) : array_pop($parts);

        return [implode(' ', $parts), $lastName];
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