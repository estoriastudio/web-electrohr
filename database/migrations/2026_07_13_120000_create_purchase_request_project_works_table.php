<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_project_works', function (Blueprint $table) {
            $table->foreignId('purchase_request_id')
                ->constrained('purchase_requests')
                ->cascadeOnDelete();
            $table->foreignId('project_work_id')
                ->constrained('project_works')
                ->cascadeOnDelete();
            $table->primary(['purchase_request_id', 'project_work_id']);
        });

        DB::table('purchase_requests')
            ->whereNotNull('project_work_id')
            ->orderBy('id')
            ->each(function ($purchaseRequest) {
                DB::table('purchase_request_project_works')->insertOrIgnore([
                    'purchase_request_id' => $purchaseRequest->id,
                    'project_work_id' => $purchaseRequest->project_work_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_project_works');
    }
};
