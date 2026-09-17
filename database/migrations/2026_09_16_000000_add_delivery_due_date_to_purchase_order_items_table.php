<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->date('delivery_due_date')->nullable()->index();
        });

        DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereNotNull('purchase_order_items.delivery_date')
            ->select([
                'purchase_order_items.id',
                'purchase_order_items.delivery_date',
                'purchase_orders.created_at as purchase_order_created_at',
            ])
            ->orderBy('purchase_order_items.id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    $deliveryDueDate = $this->resolveLegacyDeliveryDueDate(
                        $item->delivery_date,
                        $item->purchase_order_created_at,
                    );

                    if ($deliveryDueDate) {
                        DB::table('purchase_order_items')
                            ->where('id', $item->id)
                            ->update(['delivery_due_date' => $deliveryDueDate]);
                    }
                }
            }, 'purchase_order_items.id', 'id');
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex(['delivery_due_date']);
            $table->dropColumn('delivery_due_date');
        });
    }

    private function resolveLegacyDeliveryDueDate(string $deliveryDate, string $purchaseOrderCreatedAt): ?string
    {
        $value = trim($deliveryDate);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);

                if ($date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        if (preg_match('/^(\d+)\s+D[IÍ]AS?$/iu', $value, $matches)) {
            return Carbon::parse($purchaseOrderCreatedAt)->addDays((int) $matches[1])->toDateString();
        }

        if (preg_match('/^(\d+)\s+SEMANAS?$/iu', $value, $matches)) {
            return Carbon::parse($purchaseOrderCreatedAt)->addWeeks((int) $matches[1])->toDateString();
        }

        return null;
    }
};