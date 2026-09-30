<?php

use App\Services\ModulePermissionSetup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(fn () => app(ModulePermissionSetup::class)->migrateLegacyAssignments());
    }

    public function down(): void
    {
    }
};