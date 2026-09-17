<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PurchaseOrderPaymentSequenceValidator
{
    private const STATUS_RANKS = [
        'por_autorizar' => 1,
        'pospuesto' => 1,
        'autorizado' => 2,
        'pagado' => 3,
    ];

    public function ensureStatusSequence(PurchaseOrder $purchaseOrder, array $statusUpdates): void
    {
        $statusUpdates = collect($statusUpdates)
            ->mapWithKeys(fn ($status, $paymentId) => [(int) $paymentId => $status])
            ->all();
        $milestonePayments = $this->milestonePayments($purchaseOrder);

        foreach ($milestonePayments as $index => $current) {
            if ($current['payment'] === null) {
                continue;
            }

            $paymentId = $current['payment']->id;
            if (!array_key_exists($paymentId, $statusUpdates)) {
                continue;
            }

            $currentStatus = $statusUpdates[$paymentId];
            $currentRank = self::STATUS_RANKS[$currentStatus] ?? null;
            if ($currentRank === null) {
                continue;
            }

            foreach (array_slice($milestonePayments, 0, $index) as $previous) {
                if ($previous['payment'] === null) {
                    continue;
                }

                $previousStatus = $statusUpdates[$previous['payment']->id] ?? $previous['payment']->status;
                $previousRank = self::STATUS_RANKS[$previousStatus] ?? null;

                if ($previousRank !== null && $currentRank > $previousRank) {
                    throw ValidationException::withMessages([
                        'status' => "No se puede cambiar el estatus del Hito {$current['position']}: su pago inicial no puede estar más avanzado que el del Hito {$previous['position']}.",
                    ]);
                }
            }

            foreach (array_slice($milestonePayments, $index + 1) as $following) {
                if ($following['payment'] === null) {
                    continue;
                }

                $followingStatus = $statusUpdates[$following['payment']->id] ?? $following['payment']->status;
                $followingRank = self::STATUS_RANKS[$followingStatus] ?? null;

                if ($followingRank !== null && $followingRank > $currentRank) {
                    throw ValidationException::withMessages([
                        'status' => "No se puede cambiar el estatus del Hito {$current['position']}: el pago inicial del Hito {$following['position']} ya está más avanzado.",
                    ]);
                }
            }
        }
    }

    public function ensurePaymentDateSequence(PurchaseOrder $purchaseOrder, array $paymentDateUpdates, string $errorKey = 'payment_date'): void
    {
        $paymentDateUpdates = collect($paymentDateUpdates)
            ->mapWithKeys(fn ($date, $paymentId) => [(int) $paymentId => Carbon::parse($date)])
            ->all();
        $milestonePayments = $this->milestonePayments($purchaseOrder);

        foreach ($milestonePayments as $index => $current) {
            if ($current['payment'] === null) {
                continue;
            }

            $paymentId = $current['payment']->id;
            if (!array_key_exists($paymentId, $paymentDateUpdates)) {
                continue;
            }

            $currentDate = $paymentDateUpdates[$paymentId];
            $previousWithLatestDate = collect(array_slice($milestonePayments, 0, $index))
                ->filter(fn ($previous) => $previous['payment'] !== null && $this->paymentDate($previous['payment'], $paymentDateUpdates) !== null)
                ->sortByDesc(fn ($previous) => $this->paymentDate($previous['payment'], $paymentDateUpdates)->timestamp)
                ->first();

            if ($previousWithLatestDate !== null
                && $currentDate->lt($this->paymentDate($previousWithLatestDate['payment'], $paymentDateUpdates))) {
                throw ValidationException::withMessages([
                    $errorKey => "La fecha de pago del Hito {$current['position']} no puede ser anterior a la del Hito {$previousWithLatestDate['position']}.",
                ]);
            }

            $followingWithEarliestDate = collect(array_slice($milestonePayments, $index + 1))
                ->filter(fn ($following) => $following['payment'] !== null && $this->paymentDate($following['payment'], $paymentDateUpdates) !== null)
                ->sortBy(fn ($following) => $this->paymentDate($following['payment'], $paymentDateUpdates)->timestamp)
                ->first();

            if ($followingWithEarliestDate !== null
                && $currentDate->gt($this->paymentDate($followingWithEarliestDate['payment'], $paymentDateUpdates))) {
                throw ValidationException::withMessages([
                    $errorKey => "La fecha de pago del Hito {$current['position']} no puede ser posterior a la del Hito {$followingWithEarliestDate['position']}.",
                ]);
            }
        }
    }

    public function ensureNewInitialPaymentDate(PurchaseOrder $purchaseOrder, CarbonInterface|string $paymentDate, string $errorKey = 'payment_date'): void
    {
        $candidateDate = Carbon::parse($paymentDate);
        $latestPayment = collect($this->milestonePayments($purchaseOrder))
            ->filter(fn ($entry) => $entry['payment'] !== null && $entry['payment']->payment_date !== null)
            ->sortByDesc(fn ($entry) => $entry['payment']->payment_date->timestamp)
            ->first();

        if ($latestPayment !== null && $candidateDate->lt($latestPayment['payment']->payment_date)) {
            throw ValidationException::withMessages([
                $errorKey => "La fecha de pago del nuevo hito no puede ser anterior a la del Hito {$latestPayment['position']}.",
            ]);
        }
    }

    public function ensureExistingMilestoneFirstPaymentDate(
        PurchaseOrder $purchaseOrder,
        int $milestoneId,
        CarbonInterface|string $paymentDate,
        string $errorKey = 'payment_date',
    ): void {
        $candidateDate = Carbon::parse($paymentDate);
        $milestonePayments = $this->milestonePayments($purchaseOrder);
        $currentIndex = collect($milestonePayments)
            ->search(fn ($entry) => $entry['milestone']->id === $milestoneId);

        if ($currentIndex === false) {
            return;
        }
        $currentPosition = $currentIndex + 1;

        $previousWithLatestDate = collect(array_slice($milestonePayments, 0, $currentIndex))
            ->filter(fn ($entry) => $entry['payment'] !== null && $entry['payment']->payment_date !== null)
            ->sortByDesc(fn ($entry) => $entry['payment']->payment_date->timestamp)
            ->first();
        if ($previousWithLatestDate !== null && $candidateDate->lt($previousWithLatestDate['payment']->payment_date)) {
            throw ValidationException::withMessages([
                $errorKey => "La fecha de pago del Hito {$currentPosition} no puede ser anterior a la del Hito {$previousWithLatestDate['position']}.",
            ]);
        }

        $followingWithEarliestDate = collect(array_slice($milestonePayments, $currentIndex + 1))
            ->filter(fn ($entry) => $entry['payment'] !== null && $entry['payment']->payment_date !== null)
            ->sortBy(fn ($entry) => $entry['payment']->payment_date->timestamp)
            ->first();
        if ($followingWithEarliestDate !== null && $candidateDate->gt($followingWithEarliestDate['payment']->payment_date)) {
            throw ValidationException::withMessages([
                $errorKey => "La fecha de pago del Hito {$currentPosition} no puede ser posterior a la del Hito {$followingWithEarliestDate['position']}.",
            ]);
        }
    }

    private function milestonePayments(PurchaseOrder $purchaseOrder): array
    {
        $milestones = $purchaseOrder->milestones()
            ->with(['payments' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->groupBy('purchase_order_id')
            ->get($purchaseOrder->id, collect())
            ->values();

        return $milestones
            ->map(function ($milestone, $index) {
                $payments = $milestone->payments
                    ->groupBy('milestone_id')
                    ->get($milestone->id, collect());
                $payment = $payments->first();

                return [
                    'milestone' => $milestone,
                    'position' => $index + 1,
                    'payment' => $payment,
                ];
            })
            ->values()
            ->all();
    }

    private function paymentDate(object $payment, array $paymentDateUpdates): ?Carbon
    {
        return $paymentDateUpdates[$payment->id] ?? $payment->payment_date;
    }
}