<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        $defaults = [
            'day_off_types' => [
                'A' => ['pct' => null],
                'B' => ['pct' => null],
                'C' => ['pct' => null],
            ],
            'sunday' => ['divisor' => 6, 'bonus' => 50],
            'ot_deadline_hour' => 10,
            'savings_payout_date' => $now->copy()->setMonth(12)->setDay(15)->toDateString(),
        ];

        foreach ($defaults as $key => $value) {
            DB::table('hr_settings')->insert([
                'key' => $key,
                'value' => json_encode($value),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_settings');
    }
};
