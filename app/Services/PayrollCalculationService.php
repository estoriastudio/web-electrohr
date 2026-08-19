<?php

namespace App\Services;

use App\Models\PayrollLine;
use Illuminate\Support\Facades\DB;

class PayrollCalculationService
{
    public function recalculate(PayrollLine $payrollLine): PayrollLine
    {
        return DB::transaction(function () use ($payrollLine): PayrollLine {
            $line = PayrollLine::query()->lockForUpdate()->findOrFail($payrollLine->id);
            $extraPaymentsTotal = (float) $line->extraPayments()->sum('amount');
            $total = (float) $line->base_salary
                + $extraPaymentsTotal
                + (float) $line->sunday_amount
                + (float) $line->extras_amount
                + (float) $line->meal_total_amount
                - (float) $line->lost_material_amount
                - (float) $line->loan_amount
                - (float) $line->infonavit_amount
                - (float) $line->absence_amount
                - (float) $line->savings_fund_amount;

            $line->update([
                'incentive_amount' => round($extraPaymentsTotal, 2),
                'subtotal_amount' => round($total, 2),
                'total_amount' => round($total, 2),
                'complement_amount' => round($total - (float) $line->fiscal_amount, 2),
            ]);

            return $line->fresh(['extraPayments.incentive']);
        });
    }
}