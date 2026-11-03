<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEstimateInvoice extends Model
{
    protected $fillable = [
        'project_estimate_id',
        'folio',
        'invoice_date',
        'payment_date',
        'pdf_path',
        'pdf_original_name',
        'xml_path',
        'xml_original_name',
        'uploaded_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'payment_date' => 'date',
    ];

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(ProjectEstimate::class, 'project_estimate_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function storedPaths(): array
    {
        return array_values(array_filter([$this->pdf_path, $this->xml_path]));
    }
}
