<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotente: una ejecucion previa interrumpida pudo dejar la tabla creada a medias.
        if (!Schema::hasTable('purchase_order_item_deliveries')) {
            Schema::create('purchase_order_item_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
                $table->foreignId('evidence_id')->nullable()->constrained('purchase_order_evidences')->nullOnDelete();
                $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('quantity', 15, 4);
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('purchase_order_items', 'delivered_quantity')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->decimal('delivered_quantity', 15, 4)->default(0)->after('quantity');
            });
        }

        // Los conceptos ya marcados como entregados se registran como entrega completa.
        DB::table('purchase_order_items')
            ->whereNotNull('delivered_at')
            ->whereNotIn('id', DB::table('purchase_order_item_deliveries')->select('purchase_order_item_id'))
            ->orderBy('id')
            ->each(function ($item) {
                DB::table('purchase_order_item_deliveries')->insert([
                    'purchase_order_item_id' => $item->id,
                    'evidence_id' => $item->delivery_evidence_id,
                    'delivered_by' => $item->delivered_by,
                    'quantity' => $item->quantity,
                    'delivered_at' => $item->delivered_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('purchase_order_items')
            ->orderBy('id')
            ->each(function ($item) {
                DB::table('purchase_order_items')
                    ->where('id', $item->id)
                    ->update([
                        'delivered_quantity' => DB::table('purchase_order_item_deliveries')
                            ->where('purchase_order_item_id', $item->id)
                            ->sum('quantity'),
                    ]);
            });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'delivery_evidence_id')) {
                $table->dropConstrainedForeignId('delivery_evidence_id');
            }
            if (Schema::hasColumn('purchase_order_items', 'delivered_by')) {
                $table->dropConstrainedForeignId('delivered_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_evidence_id')->nullable()->constrained('purchase_order_evidences')->nullOnDelete();
            $table->dropColumn('delivered_quantity');
        });

        Schema::dropIfExists('purchase_order_item_deliveries');
    }
};
