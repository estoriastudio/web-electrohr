<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEstimateFile extends Model
{
    public const TYPES = [
        'credit_note_pdf' => ['label' => 'Nota de crédito PDF', 'icon' => 'ri-file-pdf-2-line', 'extensions' => ['pdf']],
        'credit_note_xml' => ['label' => 'Nota de crédito XML', 'icon' => 'ri-file-code-line', 'extensions' => ['xml']],
        'spei_receipt' => ['label' => 'Comprobante SPEI', 'icon' => 'ri-bank-card-line', 'extensions' => ['pdf', 'jpg', 'jpeg', 'png']],
        'liquidation_slip' => ['label' => 'Boleta de liquidación', 'icon' => 'ri-file-list-3-line', 'extensions' => ['pdf', 'jpg', 'jpeg', 'png']],
        'other' => ['label' => 'Otro', 'icon' => 'ri-file-line', 'extensions' => ['pdf', 'xml', 'jpg', 'jpeg', 'png']],
    ];

    protected $fillable = [
        'project_estimate_id',
        'file_type',
        'file_path',
        'original_name',
        'uploaded_by',
    ];

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(ProjectEstimate::class, 'project_estimate_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
