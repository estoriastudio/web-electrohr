<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MobileAsset extends Model
{
    protected $fillable = [
        'name',
        'folio',
        'policy',
        'card_number',
        'milage',
        'brand',
        'model',
        'year',
        'serial',
        'color',
        'operator',
        'asset_function',
        'type',
        'plates',
        'status',
        'photo1',
        'photo2',
        'photo3',
    ];

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)
                    ->where('type', 'mantenimiento');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MobileAssetDocument::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class)->orderByDesc('maintenance_date');
    }

    /**
     * Devuelve los tipos de documento aplicables según el tipo de bien.
     */
    public function getApplicableDocumentTypes(): array
    {
        return MobileAssetDocument::DOCS_BY_TYPE[$this->type] ?? [];
    }

    /**
     * Estado global del semáforo: el peor estado entre todos los documentos aplicables.
     * Orden de severidad: red > yellow > gray > green
     */
    public function getWorstDocumentStatus(): string
    {
        $applicable = $this->getApplicableDocumentTypes();
        if (empty($applicable)) {
            return 'gray';
        }

        $priority = ['red' => 4, 'yellow' => 3, 'gray' => 2, 'green' => 1];
        $worst = 'green';

        foreach ($applicable as $docType) {
            $doc = $this->documents->firstWhere('document_type', $docType);
            $status = $doc ? $doc->status : 'gray';
            if (($priority[$status] ?? 0) > ($priority[$worst] ?? 0)) {
                $worst = $status;
            }
        }

        return $worst;
    }

    /**
     * URL pública de una foto desde S3 (slot 1, 2 ó 3).
     */
    public function photoUrl(int $slot): ?string
    {
        $field = 'photo' . $slot;
        return $this->$field ? Storage::disk('s3')->url($this->$field) : null;
    }
}
