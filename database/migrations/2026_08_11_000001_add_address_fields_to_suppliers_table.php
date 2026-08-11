<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('street')->nullable()->after('attended_by');
            $table->string('colony')->nullable()->after('street');
            $table->string('postal_code', 20)->nullable()->after('colony');
            $table->string('city', 100)->nullable()->after('postal_code');
            $table->string('state', 100)->nullable()->after('city');
        });

        DB::table('suppliers')
            ->orderBy('id')
            ->eachById(function (object $supplier): void {
                $location = DB::table('supplier_locations')
                    ->where('supplier_id', $supplier->id)
                    ->orderBy('id')
                    ->first(['street', 'colony', 'postal_code', 'city', 'state']);

                if (!$location) {
                    return;
                }

                DB::table('suppliers')
                    ->where('id', $supplier->id)
                    ->update([
                        'street' => $location->street,
                        'colony' => $location->colony,
                        'postal_code' => $location->postal_code,
                        'city' => $location->city,
                        'state' => $location->state,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['street', 'colony', 'postal_code', 'city', 'state']);
        });
    }
};