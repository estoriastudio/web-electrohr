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
        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('folio')->unique();
            $table->string('code')->nullable();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_work_id')->constrained('project_works')->cascadeOnDelete();
            $table->string('zone');
            $table->string('delivery_address');
            $table->date('request_date');
            $table->date('need_date');
            $table->string('supply_category');
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
        Schema::dropIfExists('material_requests');
    }
};
