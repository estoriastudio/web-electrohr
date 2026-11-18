<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: Dirección autoriza OC con su usuario, y también los cambios y la cancelación de una OC
 * autorizada. Compras solicita; Dirección (o admin) autoriza o rechaza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('authorized_by')->nullable()->after('authorized_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('purchase_order_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // change | cancel
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_change_requests');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('authorized_by');
        });
    }
};
