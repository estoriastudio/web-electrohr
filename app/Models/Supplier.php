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
        'street',
        'colony',
        'postal_code',
        'city',
        'state',
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

    private function profileRequirements(): array
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

        return [
            'Razón social' => !empty($this->rfc_name),
            'Nombre comercial' => !empty($this->commercial_name),
            'RFC' => !empty($this->rfc_num),
            'Estatus' => !empty($this->status),
            'Calle' => !empty($this->street),
            'Código postal' => !empty($this->postal_code),
            'Colonia' => !empty($this->colony),
            'Ciudad' => !empty($this->city),
            'Estado' => !empty($this->state),
            'Nombre del contacto principal' => !empty($contact?->name),
            'Teléfono del contacto principal' => !empty($contact?->phone),
            'Correo electrónico del contacto principal' => !empty($contact?->email),
            'Banco' => !empty($location?->bank_name),
            'Cuenta bancaria' => !empty($location?->bank_account),
            'CLABE interbancaria' => !empty($location?->bank_clabe),
            'Moneda' => !empty($location?->currency),
            'Carátula de estado de cuenta' => !empty($location?->account_statement_path),
        ];
    }

    /**
     * Porcentaje de completitud del perfil (0-100).
     */
    public function getProfileCompletenessAttribute(): int
    {
        $requirements = $this->profileRequirements();

        return (int) round((collect($requirements)->filter()->count() / count($requirements)) * 100);
    }

    /**
     * Devuelve los campos del perfil que aún están vacíos.
     */
    public function getMissingFieldsAttribute(): array
    {
        return collect($this->profileRequirements())
            ->filter(fn (bool $filled) => !$filled)
            ->keys()
            ->all();
    }

    /**
     * Campos que deben estar completos antes de fincar una orden de compra.
     */
    public function purchaseOrderMissingFields(): array
    {
        return array_values(array_diff($this->missing_fields, [
            'Banco',
            'Cuenta bancaria',
            'CLABE interbancaria',
        ]));
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
