<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('nickname');
            $table->boolean('is_dc5')->default(false)->after('payment_type');
        });

        DB::table('workers')
            ->where('status', 'active')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('worker_files')
                    ->whereColumn('worker_files.worker_id', 'workers.id')
                    ->whereNotNull('ine_path')
                    ->whereNotNull('birth_certificate_path')
                    ->whereNotNull('address_proof_path')
                    ->whereNotNull('nss_path')
                    ->whereNotNull('license_path')
                    ->whereNotNull('tax_status_path')
                    ->whereNotNull('medical_certificate_path')
                    ->whereNotNull('cv_path')
                    ->whereNotNull('emergency_contact_ine_path');
            })
            ->update([
                'status' => 'pre_registered',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['profile_photo_path', 'is_dc5']);
        });
    }
};