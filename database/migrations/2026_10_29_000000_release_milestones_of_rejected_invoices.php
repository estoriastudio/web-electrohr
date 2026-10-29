<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Una factura rechazada ya no ocupa sus hitos: solo las facturas vigentes los bloquean.
    public function up(): void
    {
        DB::table('invoice_milestone')
            ->whereIn('purchase_order_invoice_id', DB::table('purchase_order_invoices')->where('status', 'rechazada')->select('id'))
            ->delete();
    }

    public function down(): void
    {
        // Los vínculos eliminados no se pueden reconstruir.
    }
};
