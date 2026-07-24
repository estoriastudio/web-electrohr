<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'sent_to_warehouse', 'changes_requested', 'linked', 'completed'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'sent_to_warehouse', 'linked', 'completed'])
                ->default('pending')
                ->change();
        });
    }
};
