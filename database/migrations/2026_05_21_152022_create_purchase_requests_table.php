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
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('folio')->unique();
            $table->string('code')->nullable();
            $table->foreignId('material_request_id')->nullable()->constrained('material_requests')->nullOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_work_id')->constrained('project_works')->cascadeOnDelete();
            $table->string('zone');
            $table->string('delivery_address');
            $table->string('short_description');
            $table->date('request_date');
            $table->date('need_date');
            $table->foreignId('requested_by')->constrained('users');
            $table->enum('status', ['pending', 'linked', 'completed'])->default('pending');
            $table->json('observations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
