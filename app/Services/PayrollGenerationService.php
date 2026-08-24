<?php

namespace App\Services;

use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollGenerationService
{
    public function __construct(
        private PayrollCalculationService $calculation,
        private WorkerIncentiveService $incentives,
    ) {}

    public function generateLine(PayrollPeriod $payrollPeriod, Worker $worker): PayrollLine
    {
        return DB::transaction(function () use ($payrollPeriod, $worker): PayrollLine {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($payrollPeriod->id);
            $worker = Worker::query()->lockForUpdate()->findOrFail($worker->id);

            if ($period->status !== 'open') {
                throw new DomainException('Solo se pueden generar líneas en periodos abiertos.');
            }

            if ($worker->status !== 'active' || $worker->payment_type !== 'salaried') {
                throw new DomainException('El trabajador debe estar activo y tener pago por sueldo semanal.');
            }

            if (PayrollLine::query()
                ->where('payroll_period_id', $period->id)
                ->where('worker_id', $worker->id)
                ->exists()) {
                throw new DomainException('El trabajador ya tiene una línea de nómina para este periodo.');
            }

            $attendances = WorkerAttendance::query()
                ->where('worker_id', $worker->id)
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->orderBy('date')
                ->lockForUpdate()
                ->get();
            $missingDates = $this->missingDates($period, $attendances);

            if ($missingDates->isNotEmpty()) {
                throw new DomainException('Falta asistencia para: '.$missingDates->join(', ').'.');
            }

            if ($attendances->contains(fn (WorkerAttendance $attendance) => $attendance->payroll_line_id !== null)) {
                throw new DomainException('Hay asistencias del periodo que ya pertenecen a otra línea de nómina.');
            }

            $sundayAttendance = $attendances->first(fn (WorkerAttendance $attendance) => $attendance->date->isSunday());
            $weekdayAttendances = $attendances->reject(fn (WorkerAttendance $attendance) => $attendance->date->isSunday());
            $mealDays = $weekdayAttendances->where('attended', true)->count();
            $absenceDays = $weekdayAttendances->where('attended', false)->count();
            $mealAmountPerDay = (float) config('payroll.meal_amount_per_day');
            $sundayWorked = (bool) $sundayAttendance?->attended;
            $currentGroup = $worker->currentGroup();

            $line = PayrollLine::create([
                'payroll_period_id' => $period->id,
                'worker_id' => $worker->id,
                'project_work_id' => $currentGroup?->project_work_id ?? $worker->project_work_id,
                'worker_group_id' => $currentGroup?->id,
                'position_category_id' => $worker->position_category_id,
                'base_salary' => $worker->weekly_salary,
                'meal_days' => $mealDays,
                'meal_amount_per_day' => $mealAmountPerDay,
                'meal_total_amount' => round($mealDays * $mealAmountPerDay, 2),
                'absence_days' => $absenceDays,
                'absence_amount' => round(
                    ((float) $worker->weekly_salary * (float) config('payroll.absence_monthly_factor') / (float) config('payroll.absence_month_divisor')) * $absenceDays,
                    2,
                ),
                'sunday_worked' => $sundayWorked,
                'sunday_amount' => $sundayWorked
                    ? round(
                        ((float) $worker->weekly_salary / (float) config('payroll.sunday_daily_rate_divisor')) + (float) config('payroll.sunday_bonus_amount'),
                        2,
                    )
                    : 0,
            ]);

            WorkerAttendance::query()
                ->whereIn('id', $attendances->pluck('id'))
                ->update(['payroll_line_id' => $line->id]);

            $this->incentives->applyToPayrollLine($line);

            return $line->fresh(['extraPayments.incentive']);
        });
    }

    public function rebuildLine(PayrollLine $payrollLine): PayrollLine
    {
        return DB::transaction(function () use ($payrollLine): PayrollLine {
            $line = PayrollLine::query()->with('payrollPeriod')->lockForUpdate()->findOrFail($payrollLine->id);

            if ($line->payrollPeriod->status !== 'open') {
                throw new DomainException('Solo se pueden reconstruir líneas de periodos abiertos.');
            }

            WorkerAttendance::query()->where('payroll_line_id', $line->id)->update(['payroll_line_id' => null]);
            $period = $line->payrollPeriod;
            $worker = Worker::findOrFail($line->worker_id);
            $line->delete();

            return $this->generateLine($period, $worker);
        });
    }

    public function missingDates(PayrollPeriod $payrollPeriod, Collection $attendances): Collection
    {
        $presentDates = $attendances
            ->map(fn (WorkerAttendance $attendance) => $attendance->date->toDateString())
            ->flip();

        return $this->periodDates($payrollPeriod)
            ->reject(fn (Carbon $date) => $presentDates->has($date->toDateString()))
            ->map(fn (Carbon $date) => $date->format('d/m/Y'))
            ->values();
    }

    private function periodDates(PayrollPeriod $payrollPeriod): Collection
    {
        $startDate = $payrollPeriod->start_date->copy();
        $days = $startDate->diffInDays($payrollPeriod->end_date);

        return collect(range(0, $days))->map(fn (int $offset) => $startDate->copy()->addDays($offset));
    }
}