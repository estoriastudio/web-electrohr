<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_assets', function (Blueprint $table) {
            $table->string('policy')->nullable()->after('folio');
            $table->string('card_number')->nullable()->after('policy');
            $table->string('milage')->nullable()->after('card_number');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_assets', function (Blueprint $table) {
            $table->dropColumn(['policy', 'card_number', 'milage']);
        });
    }
};
