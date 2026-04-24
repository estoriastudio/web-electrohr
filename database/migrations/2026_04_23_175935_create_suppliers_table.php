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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('rfc_name');
            $table->string('commercial_name')->nullable();
            
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('rfc_num')->nullable();
            $table->text('address')->nullable();
            $table->string('attended_by')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_clabe')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('currency')->nullable(); // MXN, USD, EUR, etc.

            $table->string('status')->nullable(); // active, inactive, blacklisted, etc.

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
