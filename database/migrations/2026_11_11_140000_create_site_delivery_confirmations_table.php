<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 (V-06): confirmación de entrega en obra. Cierra el movimiento Almacén → obra: Gerente o
 * Supervisor registran lo recibido contra lo que salió de Almacén (salida definitiva a una obra).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_delivery_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_exit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20); // received | discrepancy
            $table->text('notes')->nullable();
            $table->foreignId('confirmed_by')->constrained('users');
            $table->timestamp('confirmed_at');
            $table->timestamps();
        });

        Schema::create('site_delivery_confirmation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_delivery_confirmation_id');
            $table->foreign('site_delivery_confirmation_id', 'sdc_items_confirmation_fk')->references('id')->on('site_delivery_confirmations')->cascadeOnDelete();
            $table->foreignId('stock_exit_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('concept_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('sent_quantity', 12, 3);
            $table->decimal('received_quantity', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_delivery_confirmation_items');
        Schema::dropIfExists('site_delivery_confirmations');
    }
};
