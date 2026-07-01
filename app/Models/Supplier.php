<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'commercial_name',
        'rfc_name',
        'rfc_num',
        'email',
        'phone',
        'cellphone',
        'address',
        'attended_by',
        'bank_name',
        'bank_account',
        'bank_clabe',
        'swift_code',
        'currency',
        'status',
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
            // Teléfono: Supplier o contacto
            !empty($this->phone)         || !empty($contact?->phone),
            // Correo: Supplier o contacto
            !empty($this->email)         || !empty($contact?->email),
            // Dirección: Supplier o primera sucursal
            !empty($this->address)       || !empty($location?->street),
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
            if (empty($this->phone)  && empty($contact->phone))  $missing[] = 'Teléfono';
            if (empty($this->email)  && empty($contact->email))  $missing[] = 'Correo electrónico';
        }

        // Datos de sucursal
        if ($location === null) {
            $missing[] = 'Al menos una sucursal';
        } else {
            if (empty($this->address)      && empty($location->street))       $missing[] = 'Dirección';
            if (empty($this->bank_name)    && empty($location->bank_name))    $missing[] = 'Banco';
            if (empty($this->bank_account) && empty($location->bank_account)) $missing[] = 'Cuenta bancaria';
            if (empty($this->bank_clabe)   && empty($location->bank_clabe))   $missing[] = 'CLABE interbancaria';
            if (empty($this->currency)     && empty($location->currency))     $missing[] = 'Moneda';
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
}
