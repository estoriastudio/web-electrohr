<?php

namespace App\Services;

use App\Models\Incentive;
use App\Models\PayrollLine;
use App\Models\PayrollLineExtraPayment;
use App\Models\PayrollPeriod;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkerIncentiveService
{
    public function __construct(private PayrollCalculationService $calculation) {}

    public function applyToPayrollLine(PayrollLine $payrollLine): void
    {
        $payrollLine->loadMissing('payrollPeriod');
        $incentives = Incentive::query()
            ->where('worker_id', $payrollLine->worker_id)
            ->where('status', 'active')
            ->whereBetween('incentive_date', [$payrollLine->payrollPeriod->start_date, $payrollLine->payrollPeriod->end_date])
            ->orderBy('incentive_date')
            ->get();

        foreach ($incentives as $incentive) {
            PayrollLineExtraPayment::updateOrCreate(
                ['incentive_id' => $incentive->id],
                [
                    'payroll_line_id' => $payrollLine->id,
                    'amount' => $this->amountFor($incentive, (float) $payrollLine->base_salary),
                ],
            );
        }

        $this->calculation->recalculate($payrollLine);
    }

    public function sync(Incentive $incentive): void
    {
        DB::transaction(function () use ($incentive): void {
            $incentive = Incentive::query()->lockForUpdate()->findOrFail($incentive->id);
            $payments = $incentive->extraPayments()
                ->with('payrollLine.payrollPeriod')
                ->lockForUpdate()
                ->get();
            $affectedLines = new Collection;

            foreach ($payments as $payment) {
                if ($payment->payrollLine->payrollPeriod->status !== 'open') {
                    throw new DomainException('No se puede modificar un incentivo que ya pertenece a un periodo cerrado o pagado.');
                }

                $affectedLines->push($payment->payrollLine);
                $payment->delete();
            }

            $this->ensurePeriodOpen($incentive);
            $period = $this->periodFor($incentive);

            if ($period && $incentive->status === 'active') {
                $line = PayrollLine::query()
                    ->where('payroll_period_id', $period->id)
                    ->where('worker_id', $incentive->worker_id)
                    ->lockForUpdate()
                    ->first();

                if ($line) {
                    PayrollLineExtraPayment::create([
                        'payroll_line_id' => $line->id,
                        'incentive_id' => $incentive->id,
                        'amount' => $this->amountFor($incentive, (float) $line->base_salary),
                    ]);
                    $affectedLines->push($line);
                }
            }

            $affectedLines->unique('id')->each(fn (PayrollLine $line) => $this->calculation->recalculate($line));
        });
    }

    public function ensureMutable(Incentive $incentive): void
    {
        $hasLockedPayment = $incentive->extraPayments()
            ->whereHas('payrollLine.payrollPeriod', fn ($query) => $query->whereIn('status', ['closed', 'paid']))
            ->exists();

        if ($hasLockedPayment) {
            throw new DomainException('No se puede modificar un incentivo que ya pertenece a un periodo cerrado o pagado.');
        }
    }

    public function ensurePeriodOpen(Incentive $incentive): void
    {
        $period = $this->periodFor($incentive);

        if ($period && $period->status !== 'open') {
            throw new DomainException('No se puede registrar un incentivo dentro de un periodo cerrado o pagado.');
        }
    }

    public function remove(Incentive $incentive): void
    {
        DB::transaction(function () use ($incentive): void {
            $payments = $incentive->extraPayments()
                ->with('payrollLine.payrollPeriod')
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                if ($payment->payrollLine->payrollPeriod->status !== 'open') {
                    throw new DomainException('No se puede eliminar un incentivo que ya pertenece a un periodo cerrado o pagado.');
                }
            }

            $lines = $payments->pluck('payrollLine')->unique('id');
            $payments->each->delete();
            $incentive->delete();
            $lines->each(fn (PayrollLine $line) => $this->calculation->recalculate($line));
        });
    }

    public function amountFor(Incentive $incentive, float $baseSalary): float
    {
        if ($incentive->category === 'overtime'
            && $incentive->overtime_hours !== null
            && $incentive->overtime_hourly_rate !== null) {
            return round((float) $incentive->overtime_hours * (float) $incentive->overtime_hourly_rate, 2);
        }

        $rate = data_get(config('payroll.incentive_rates'), "{$incentive->category}.{$incentive->rate_type}");

        if ($rate === null) {
            throw new DomainException('La combinación de categoría y tipo de incentivo no tiene una fórmula configurada.');
        }

        return round($baseSalary * (float) $rate, 2);
    }

    private function periodFor(Incentive $incentive): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->whereDate('start_date', '<=', $incentive->incentive_date)
            ->whereDate('end_date', '>=', $incentive->incentive_date)
            ->first();
    }
}