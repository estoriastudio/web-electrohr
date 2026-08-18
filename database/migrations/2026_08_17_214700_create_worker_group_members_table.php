<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->index(['worker_id', 'left_at']);
            $table->index(['worker_group_id', 'left_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_group_members');
    }
};