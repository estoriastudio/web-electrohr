<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('ine_path')->nullable();
            $table->string('birth_certificate_path')->nullable();
            $table->string('address_proof_path')->nullable();
            $table->string('nss_path')->nullable();
            $table->string('license_path')->nullable();
            $table->string('tax_status_path')->nullable();
            $table->string('medical_certificate_path')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('emergency_contact_ine_path')->nullable();
            $table->date('birth_certificate_expiration_date')->nullable();
            $table->date('address_proof_expiration_date')->nullable();
            $table->date('nss_expiration_date')->nullable();
            $table->date('license_expiration_date')->nullable();
            $table->date('tax_status_expiration_date')->nullable();
            $table->date('cv_expiration_date')->nullable();
            $table->date('emergency_contact_ine_expiration_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_files');
    }
};