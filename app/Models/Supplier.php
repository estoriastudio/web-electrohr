<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    protected $fillable = [
        'commercial_name',
        'rfc_name',
        'rfc_num',
        'attended_by',
        'bank_name',
        'bank_account',
        'bank_clabe',
        'swift_code',
        'currency',
        'status',
        'portal_user_id',
        'portal_access_enabled',
        'portal_access_activated_at',
        'portal_access_deactivated_at',
        'portal_access_managed_by',
    ];

    protected $casts = [
        'portal_access_enabled' => 'boolean',
        'portal_access_activated_at' => 'datetime',
        'portal_access_deactivated_at' => 'datetime',
    ];

    /**
     * Porcentaje de completitud del perfil (0–100).
     * Considera campos del modelo Supplier, contactos y sucursales.
     */
    public function getProfileCompletenessAttribute(): int
    {
        if ($this->relationLoaded('contacts')) {
            $contact = $this->contacts->firstWhere('is_primary', true) ?? $this->contacts->first();
        } else {
            $contact = $this->contacts()->where('is_primary', true)->first()
                ?? $this->contacts()->first();
        }

        $location = $this->relationLoaded('locations')
            ? $this->locations->first()
            : $this->locations()->first();

        $checks = [
            !empty($this->rfc_name),
            !empty($this->commercial_name),
            !empty($this->rfc_num),
            !empty($this->attended_by),
            !empty($this->status),
            // Teléfono del contacto
            !empty($contact?->phone),
            // Correo del contacto
            !empty($contact?->email),
            // Dirección del registro bancario
            !empty($location?->street),
            // Contacto registrado
            $contact !== null,
            // Sucursal registrada
            $location !== null,
            // Banco: Supplier o primera sucursal
            !empty($this->bank_name)     || !empty($location?->bank_name),
            // Cuenta: Supplier o primera sucursal
            !empty($this->bank_account)  || !empty($location?->bank_account),
            // CLABE: Supplier o primera sucursal
            !empty($this->bank_clabe)    || !empty($location?->bank_clabe),
            // Moneda: Supplier o primera sucursal
            !empty($this->currency)      || !empty($location?->currency),
        ];

        $filled = collect($checks)->filter()->count();

        return (int) round(($filled / count($checks)) * 100);
    }

    /**
     * Devuelve los campos del perfil que aún están vacíos,
     * incluyendo la ausencia de contactos y sucursales.
     */
    public function getMissingFieldsAttribute(): array
    {
        if ($this->relationLoaded('contacts')) {
            $contact = $this->contacts->firstWhere('is_primary', true) ?? $this->contacts->first();
        } else {
            $contact = $this->contacts()->where('is_primary', true)->first()
                ?? $this->contacts()->first();
        }

        $location = $this->relationLoaded('locations')
            ? $this->locations->first()
            : $this->locations()->first();

        $missing = [];

        if (empty($this->rfc_name))        $missing[] = 'Razón social';
        if (empty($this->commercial_name)) $missing[] = 'Nombre comercial';
        if (empty($this->rfc_num))         $missing[] = 'RFC';
        if (empty($this->attended_by))     $missing[] = 'Atendido por';
        if (empty($this->status))          $missing[] = 'Estatus';

        // Datos de contacto
        if ($contact === null) {
            $missing[] = 'Al menos un contacto';
        } else {
            if (empty($contact->phone))  $missing[] = 'Teléfono';
            if (empty($contact->email))  $missing[] = 'Correo electrónico';
        }

        // Datos bancarios
        if ($location === null) {
            $missing[] = 'Al menos una cuenta bancaria';
        } else {
            if (empty($location->street))       $missing[] = 'Dirección';
            if (empty($location->bank_name))    $missing[] = 'Banco';
            if (empty($location->bank_account)) $missing[] = 'Cuenta bancaria';
            if (empty($location->bank_clabe))   $missing[] = 'CLABE interbancaria';
            if (empty($location->currency))     $missing[] = 'Moneda';
        }

        return $missing;
    }

    /**
     * Campos que deben estar completos antes de fincar una orden de compra.
     */
    public function purchaseOrderMissingFields(): array
    {
        $missing = [];

        $generalFields = [
            'rfc_name'        => 'Razón social',
            'commercial_name' => 'Nombre comercial',
            'rfc_num'         => 'RFC',
            'attended_by'     => 'Atendido por',
        ];

        foreach ($generalFields as $field => $label) {
            if (empty($this->{$field})) {
                $missing[] = $label;
            }
        }

        if (!$this->contacts()->exists()) {
            $missing[] = 'Al menos un contacto';
        }

        if (!$this->locations()->exists()) {
            $missing[] = 'Al menos un registro de datos bancarios';
        }

        return $missing;
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function materialVouchers()
    {
        return $this->hasMany(MaterialVoucher::class);
    }

    public function contacts()
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function primaryContact()
    {
        return $this->hasOne(SupplierContact::class)->where('is_primary', true);
    }

    public function locations()
    {
        return $this->hasMany(SupplierLocation::class);
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'portal_user_id');
    }

    public function portalAccessManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'portal_access_managed_by');
    }
}
