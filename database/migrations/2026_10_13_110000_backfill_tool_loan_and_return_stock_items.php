<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('stock_exits')
            ->where('exit_type', 'tool_loan')
            ->whereNotNull('concept_id')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('stock_exit_items')->whereColumn('stock_exit_items.stock_exit_id', 'stock_exits.id'))
            ->orderBy('id')
            ->get(['id', 'concept_id', 'quantity'])
            ->each(fn ($exit) => DB::table('stock_exit_items')->insert([
                'stock_exit_id' => $exit->id, 'concept_id' => $exit->concept_id, 'line_number' => 1,
                'quantity' => $exit->quantity, 'created_at' => $now, 'updated_at' => $now,
            ]));

        DB::table('stock_entries')
            ->where('entry_type', 'tool_return')
            ->whereNotNull('concept_id')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('stock_entry_items')->whereColumn('stock_entry_items.stock_entry_id', 'stock_entries.id'))
            ->orderBy('id')
            ->get(['id', 'concept_id', 'quantity'])
            ->each(fn ($entry) => DB::table('stock_entry_items')->insert([
                'stock_entry_id' => $entry->id, 'concept_id' => $entry->concept_id, 'line_number' => 1,
                'quantity' => $entry->quantity, 'created_at' => $now, 'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        // Los renglones se conservan: no se distinguen de los capturados con el flujo nuevo.
    }
};
