<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('workers')
            ->select(['id', 'bank_account'])
            ->whereNull('employee_code')
            ->whereNotNull('bank_account')
            ->where('bank_account', '<>', '')
            ->orderBy('id')
            ->each(function (object $worker): void {
                $accountExists = DB::table('workers')
                    ->where('employee_code', $worker->bank_account)
                    ->exists();

                if (! $accountExists) {
                    DB::table('workers')
                        ->where('id', $worker->id)
                        ->update(['employee_code' => $worker->bank_account]);
                }
            });
    }

    public function down(): void
    {
    }
};