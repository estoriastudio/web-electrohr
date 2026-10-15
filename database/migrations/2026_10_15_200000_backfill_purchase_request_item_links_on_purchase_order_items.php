<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * OC antiguas creadas desde una SOLCOM sin vínculo por concepto: se enlaza cada
     * partida con el concepto de la SOLCOM (mismo concept_id, o misma descripción y unidad)
     * que aún no esté heredado, para que el seguimiento de herencia las reconozca.
     */
    public function up(): void
    {
        $orders = DB::table('purchase_orders')
            ->whereNotNull('purchase_request_id')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'purchase_request_id']);

        foreach ($orders as $order) {
            $orderItems = DB::table('purchase_order_items')
                ->where('purchase_order_id', $order->id)
                ->orderBy('id')
                ->get(['id', 'concept_id', 'description', 'unit', 'purchase_request_item_id']);

            // Solo OC completamente sin vínculo (anteriores al seguimiento por concepto).
            if ($orderItems->isEmpty() || $orderItems->whereNotNull('purchase_request_item_id')->isNotEmpty()) {
                continue;
            }

            $taken = DB::table('purchase_order_items')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
                ->whereNull('purchase_orders.deleted_at')
                ->whereNotNull('purchase_order_items.purchase_request_item_id')
                ->pluck('purchase_order_items.purchase_request_item_id')
                ->all();

            $requestItems = DB::table('purchase_request_items')
                ->where('purchase_request_id', $order->purchase_request_id)
                ->orderBy('id')
                ->get(['id', 'concept_id', 'description', 'unit'])
                ->reject(fn ($item) => in_array($item->id, $taken, true))
                ->values();

            foreach ($orderItems as $orderItem) {
                $match = $requestItems->first(function ($requestItem) use ($orderItem) {
                    if ($orderItem->concept_id !== null && $requestItem->concept_id !== null) {
                        return (int) $orderItem->concept_id === (int) $requestItem->concept_id;
                    }

                    return $requestItem->description === $orderItem->description
                        && $requestItem->unit === $orderItem->unit;
                });

                if (!$match) {
                    continue;
                }

                DB::table('purchase_order_items')
                    ->where('id', $orderItem->id)
                    ->update(['purchase_request_item_id' => $match->id]);

                $requestItems = $requestItems->reject(fn ($item) => $item->id === $match->id)->values();
            }
        }
    }

    public function down(): void
    {
        // Datos de vínculo: no se revierte para no perder el seguimiento ya registrado.
    }
};
