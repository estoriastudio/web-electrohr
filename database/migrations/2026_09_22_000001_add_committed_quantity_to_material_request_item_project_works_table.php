<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_item_project_works', function (Blueprint $table) {
            $table->decimal('committed_quantity', 15, 2)->default(0)->after('quantity');
        });

        DB::table('material_request_item_project_works')
            ->where('is_committed', true)
            ->update(['committed_quantity' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        Schema::table('material_request_item_project_works', function (Blueprint $table) {
            $table->dropColumn('committed_quantity');
        });
    }
};