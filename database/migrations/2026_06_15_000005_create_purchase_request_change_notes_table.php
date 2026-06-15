<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_change_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')
                  ->constrained('purchase_requests')
                  ->cascadeOnDelete();
            $table->foreignId('requested_by')
                  ->constrained('users');
            $table->text('text');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_change_notes');
    }
};
