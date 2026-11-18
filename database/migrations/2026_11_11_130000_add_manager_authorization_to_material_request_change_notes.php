<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: cambio de SOLMAT pedido desde la obra (note_type `site_change`). Si lo pide el Supervisor
 * espera la autorización del Gerente del proyecto; autorizado, Almacén lo aplica (cancelar y sustituir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_change_notes', function (Blueprint $table) {
            $table->string('authorization_status', 20)->nullable()->after('payload'); // pending | approved | rejected
            $table->foreignId('authorized_by')->nullable()->after('authorization_status')->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable()->after('authorized_by');
            $table->text('authorization_note')->nullable()->after('authorized_at');
        });
    }

    public function down(): void
    {
        Schema::table('material_request_change_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('authorized_by');
            $table->dropColumn(['authorization_status', 'authorized_at', 'authorization_note']);
        });
    }
};
