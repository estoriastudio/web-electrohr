<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderAnnex extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'client_name',
        'provider_name',
        'annex_condiciones',
        'penalidad_porcentaje',
        'penalidad_numero',
        'nombre_aceptacion',
        'annex_contrato',
        'contrato_html',
        'annex_dossier',
        'dossier_html',
    ];

    protected $casts = [
        'annex_condiciones' => 'boolean',
        'annex_contrato'    => 'boolean',
        'annex_dossier'     => 'boolean',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
