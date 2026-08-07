<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('suppliers')->orderBy('id')->each(function (object $supplier): void {
            $contact = DB::table('supplier_contacts')
                ->where('supplier_id', $supplier->id)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->first();

            if ($contact) {
                DB::table('supplier_contacts')->where('id', $contact->id)->update([
                    'email' => $contact->email ?: $supplier->email,
                    'phone' => $contact->phone ?: $supplier->phone,
                    'updated_at' => now(),
                ]);
            } elseif ($supplier->email || $supplier->phone) {
                DB::table('supplier_contacts')->insert([
                    'supplier_id' => $supplier->id,
                    'name' => 'Contacto principal',
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $location = DB::table('supplier_locations')
                ->where('supplier_id', $supplier->id)
                ->orderBy('id')
                ->first();

            if ($location && empty($location->street) && $supplier->address) {
                DB::table('supplier_locations')
                    ->where('id', $location->id)
                    ->update(['street' => $supplier->address, 'updated_at' => now()]);
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone', 'cellphone', 'address']);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cellphone')->nullable();
            $table->text('address')->nullable();
        });
    }
};