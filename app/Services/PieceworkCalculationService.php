<?php

namespace App\Services;

use App\Models\PieceworkWeeklyEntry;
use Illuminate\Support\Facades\DB;

class PieceworkCalculationService
{
    public function recalculate(PieceworkWeeklyEntry $pieceworkWeeklyEntry): PieceworkWeeklyEntry
    {
        return DB::transaction(function () use ($pieceworkWeeklyEntry): PieceworkWeeklyEntry {
            $entry = PieceworkWeeklyEntry::query()->lockForUpdate()->findOrFail($pieceworkWeeklyEntry->id);
            $total = (float) $entry->dailyAmounts()->sum('amount') + (float) $entry->meals_amount;

            $entry->update(['total_amount' => round($total, 2)]);

            return $entry->fresh('dailyAmounts');
        });
    }
}