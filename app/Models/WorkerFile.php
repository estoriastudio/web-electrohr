<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerFile extends Model
{
    public const REQUIRED_DOCUMENT_COLUMNS = [
        'ine_path',
        'birth_certificate_path',
        'address_proof_path',
        'nss_path',
        'license_path',
        'tax_status_path',
        'medical_certificate_path',
        'cv_path',
        'emergency_contact_ine_path',
    ];

    public const DOCUMENT_COLUMNS = [
        'ine_path',
        'birth_certificate_path',
        'address_proof_path',
        'nss_path',
        'license_path',
        'tax_status_path',
        'medical_certificate_path',
        'cv_path',
        'emergency_contact_ine_path',
        'professional_title_path',
        'professional_license_path',
    ];

    protected $fillable = [
        'worker_id',
        'ine_path',
        'birth_certificate_path',
        'address_proof_path',
        'nss_path',
        'license_path',
        'tax_status_path',
        'medical_certificate_path',
        'cv_path',
        'emergency_contact_ine_path',
        'professional_title_path',
        'professional_license_path',
        'birth_certificate_expiration_date',
        'address_proof_expiration_date',
        'nss_expiration_date',
        'license_expiration_date',
        'tax_status_expiration_date',
        'cv_expiration_date',
        'emergency_contact_ine_expiration_date',
        'notes',
    ];

    protected $casts = [
        'birth_certificate_expiration_date' => 'date',
        'address_proof_expiration_date' => 'date',
        'nss_expiration_date' => 'date',
        'license_expiration_date' => 'date',
        'tax_status_expiration_date' => 'date',
        'cv_expiration_date' => 'date',
        'emergency_contact_ine_expiration_date' => 'date',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function isComplete(): bool
    {
        foreach (self::REQUIRED_DOCUMENT_COLUMNS as $column) {
            if (blank($this->{$column})) {
                return false;
            }
        }

        return true;
    }
}