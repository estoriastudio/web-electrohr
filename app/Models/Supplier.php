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
     */
    public function getProfileCompletenessAttribute(): int
    {
        $fields = [
            'commercial_name', 'rfc_name', 'rfc_num', 'email',
            'phone', 'cellphone', 'address', 'attended_by',
            'bank_name', 'bank_account', 'bank_clabe',
            'swift_code', 'currency', 'status',
        ];

        $filled = collect($fields)->filter(fn ($f) => !empty($this->$f))->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    /**
     * Devuelve los campos del perfil que aún están vacíos.
     */
    public function getMissingFieldsAttribute(): array
    {
        $labels = [
            'commercial_name' => 'Nombre comercial',
            'rfc_name'        => 'Razón social (RFC)',
            'rfc_num'         => 'RFC',
            'email'           => 'Correo electrónico',
            'phone'           => 'Teléfono',
            'cellphone'       => 'Celular',
            'address'         => 'Dirección',
            'attended_by'     => 'Atendido por',
            'bank_name'       => 'Banco',
            'bank_account'    => 'Cuenta bancaria',
            'bank_clabe'      => 'CLABE interbancaria',
            'swift_code'      => 'Código SWIFT',
            'currency'        => 'Moneda',
            'status'          => 'Estatus',
        ];

        return collect($labels)
            ->filter(fn ($label, $field) => empty($this->$field))
            ->values()
            ->toArray();
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
