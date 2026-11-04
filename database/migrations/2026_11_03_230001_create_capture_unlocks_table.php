<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capture_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_work_id')->constrained('project_works')->cascadeOnDelete();
            $table->date('date');
            $table->text('reason');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['project_work_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_unlocks');
    }
};
