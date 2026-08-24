<?php

namespace App\Imports;

use App\Models\Worker;
use App\Models\WorkerTermination;
use App\Models\PositionCategory;
use App\Services\WorkerTerminationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

class WorkerTerminationImport implements ToCollection
{
    private array $summary = [
        'created' => 0,
        'skipped' => 0,
        'warnings' => 0,
    ];

    public function __construct(private WorkerTerminationService $terminationService) {}

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
            $location = $this->normalize($this->value($values, $headers['location']));

            if (! preg_match('/^\d+$/', $employeeCode) || ! $this->isRest($location)) {
                $this->summary['skipped']++;
                $this->summary['warnings']++;
                continue;
            }

            $worker = Worker::where('employee_code', $employeeCode)->first();
            $terminationDate = $this->date($this->value($values, $headers['termination_date']));

            if (! $worker || ! $terminationDate) {
                $this->summary['skipped']++;
                $this->summary['warnings']++;
                continue;
            }

            $exists = WorkerTermination::query()
                ->where('worker_id', $worker->id)
                ->where('termination_type', 'rest')
                ->whereDate('termination_date', $terminationDate->toDateString())
                ->exists();

            if ($exists) {
                $this->summary['skipped']++;
                continue;
            }

            $salary = $this->decimal($this->value($values, $headers['salary']));

            $this->terminationService->terminate($worker, [
                'position_category_id' => $this->positionCategoryId($this->value($values, $headers['position_category'])),
                'salary' => $salary,
                'termination_type' => 'rest',
                'reason' => $this->value($values, $headers['reason']) ?: null,
                'termination_date' => $terminationDate->toDateString(),
                'notes' => 'Importado desde BAJAS 2026.',
            ]);

            $this->summary['created']++;
        }
    }

    public function summary(): array
    {
        return $this->summary;
    }

    private function detectHeaders(array $values): ?array
    {
        $headers = array_map(fn ($value) => $this->normalize($value), $values);
        $location = $this->findIndex($headers, fn ($header) => $header === 'LUGAR');
        $employeeCode = $this->findIndex($headers, fn ($header) => str_contains($header, 'CUENTA'));
        $positionCategory = $this->findIndex($headers, fn ($header) => $header === 'CATEGORIA');
        $salary = $this->findIndex($headers, fn ($header) => $header === 'SUELDO');
        $terminationDate = $this->findIndex($headers, fn ($header) => str_contains($header, 'DIA DE BAJA'));
        $reason = $this->findIndex($headers, fn ($header) => str_contains($header, 'PORQUE SE FUE'));

        if ($location === null || $employeeCode === null || $terminationDate === null) {
            return null;
        }

        return compact('location', 'employeeCode', 'positionCategory', 'salary', 'terminationDate', 'reason') + [
            'employee_code' => $employeeCode,
            'position_category' => $positionCategory,
            'termination_date' => $terminationDate,
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

    private function isRest(string $location): bool
    {
        return str_contains($location, 'DESCANSO') || str_contains($location, 'DESACANSO');
    }

    private function decimal(string $value): ?float
    {
        $normalized = preg_replace('/[^\d.-]/', '', str_replace(',', '', $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function date(string $value): ?Carbon
    {
        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function normalize(mixed $value): string
    {
        $value = Str::upper(Str::ascii(trim((string) $value)));
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);

        return trim($value);
    }

    private function positionCategoryId(string $name): ?int
    {
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';

        if ($name === '') {
            return null;
        }

        return PositionCategory::firstOrCreate(['name' => $name], ['active' => true])->id;
    }
}