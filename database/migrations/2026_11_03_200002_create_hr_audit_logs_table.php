<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role');
            $table->string('action');
            $table->string('entity');
            $table->string('entity_id')->nullable();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->text('before')->nullable();
            $table->text('after')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('sensitive')->default(false);
            $table->timestamps();

            $table->index('entity');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_audit_logs');
    }
};
