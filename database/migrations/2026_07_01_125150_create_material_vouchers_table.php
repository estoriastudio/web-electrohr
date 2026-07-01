<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('material_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->string('folio_prefix', 16);
            $table->unsignedInteger('folio_number');
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('project_work_id')->nullable()->constrained('project_works')->nullOnDelete();
            $table->date('voucher_date');
            $table->enum('status', ['emitido', 'autorizado', 'completado', 'facturado', 'pagado'])->default('emitido');
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->string('authorized_signature_name')->nullable();
            $table->json('observations')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['folio_prefix', 'folio_number']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('material_voucher_folio_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 16)->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('material_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_voucher_id')->constrained('material_vouchers')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50);
            $table->string('description', 500);
            $table->timestamps();

            $table->index('material_voucher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_voucher_items');
        Schema::dropIfExists('material_voucher_folio_sequences');
        Schema::dropIfExists('material_vouchers');
    }
};
