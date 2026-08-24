<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piecework_daily_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piecework_weekly_entry_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('day_of_week');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['piecework_weekly_entry_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piecework_daily_amounts');
    }
};