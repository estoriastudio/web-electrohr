<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_change_notes', function (Blueprint $table) {
            $table->string('note_type', 40)->default('change_request')->after('text');
            $table->json('payload')->nullable()->after('note_type');
        });
    }

    public function down(): void
    {
        Schema::table('material_request_change_notes', function (Blueprint $table) {
            $table->dropColumn(['note_type', 'payload']);
        });
    }
};
