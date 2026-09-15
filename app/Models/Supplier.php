<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    public const PROFILE_REQUIREMENTS = [
        'rfc_name' => ['label' => 'Razón social', 'source' => 'supplier', 'column' => 'rfc_name', 'icon' => 'ri-building-line'],
        'commercial_name' => ['label' => 'Nombre comercial', 'source' => 'supplier', 'column' => 'commercial_name', 'icon' => 'ri-store-2-line'],
        'rfc_num' => ['label' => 'RFC', 'source' => 'supplier', 'column' => 'rfc_num', 'icon' => 'ri-file-text-line'],
        'status' => ['label' => 'Estatus', 'source' => 'supplier', 'column' => 'status', 'icon' => 'ri-checkbox-circle-line'],
        'street' => ['label' => 'Calle', 'source' => 'supplier', 'column' => 'street', 'icon' => 'ri-road-map-line'],
        'postal_code' => ['label' => 'Código postal', 'source' => 'supplier', 'column' => 'postal_code', 'icon' => 'ri-map-pin-line'],
        'colony' => ['label' => 'Colonia', 'source' => 'supplier', 'column' => 'colony', 'icon' => 'ri-community-line'],
        'city' => ['label' => 'Ciudad', 'source' => 'supplier', 'column' => 'city', 'icon' => 'ri-building-4-line'],
        'state' => ['label' => 'Estado', 'source' => 'supplier', 'column' => 'state', 'icon' => 'ri-map-2-line'],
        'contact_name' => ['label' => 'Nombre del contacto principal', 'source' => 'contact', 'column' => 'name', 'icon' => 'ri-user-line'],
        'contact_phone' => ['label' => 'Teléfono del contacto principal', 'source' => 'contact', 'column' => 'phone', 'icon' => 'ri-phone-line'],
        'contact_email' => ['label' => 'Correo electrónico del contacto principal', 'source' => 'contact', 'column' => 'email', 'icon' => 'ri-mail-line'],
        'bank_name' => ['label' => 'Banco', 'source' => 'location', 'column' => 'bank_name', 'icon' => 'ri-bank-line'],
        'bank_account' => ['label' => 'Cuenta bancaria', 'source' => 'location', 'column' => 'bank_account', 'icon' => 'ri-bank-card-line'],
        'bank_clabe' => ['label' => 'CLABE interbancaria', 'source' => 'location', 'column' => 'bank_clabe', 'icon' => 'ri-secure-payment-line'],
        'currency' => ['label' => 'Moneda', 'source' => 'location', 'column' => 'currency', 'icon' => 'ri-money-dollar-circle-line'],
        'account_statement' => ['label' => 'Carátula de estado de cuenta', 'source' => 'location', 'column' => 'account_statement_path', 'icon' => 'ri-file-shield-2-line'],
    ];

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

    public static function profileRequirement(string $key): ?array
    {
        return self::PROFILE_REQUIREMENTS[$key] ?? null;
    }

    public function scopeMissingProfileRequirement($query, string $key)
    {
        $requirement = self::profileRequirement($key);

        if (!$requirement) {
            return $query;
        }

        if ($requirement['source'] === 'supplier') {
            return $query->where(function ($missingQuery) use ($requirement) {
                $missingQuery->whereNull($requirement['column'])
                    ->orWhere($requirement['column'], '');
            });
        }

        $relation = $requirement['source'] === 'contact' ? 'contacts' : 'locations';

        return $query->whereDoesntHave($relation, function ($relatedQuery) use ($requirement) {
            $relatedQuery->whereNotNull($requirement['column'])
                ->where($requirement['column'], '!=', '');
        });
    }

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

        return collect(self::PROFILE_REQUIREMENTS)
            ->mapWithKeys(function (array $requirement) use ($contact, $location) {
                $model = match ($requirement['source']) {
                    'supplier' => $this,
                    'contact' => $contact,
                    'location' => $location,
                };

                return [$requirement['label'] => !empty($model?->{$requirement['column']})];
            })
            ->all();
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
