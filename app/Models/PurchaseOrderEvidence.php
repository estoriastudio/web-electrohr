<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderEvidence extends Model
{
    public const STORAGE_DISK = 's3';

    protected $table = 'purchase_order_evidences';

    protected $fillable = [
        'purchase_order_id',
        'purchase_order_milestone_id',
        'purchase_order_invoice_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'mime_type',
        'source',
        'description',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderMilestone::class, 'purchase_order_milestone_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderInvoice::class, 'purchase_order_invoice_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public static function storeUpload(UploadedFile $file, int $purchaseOrderId, string $fileName): string|false
    {
        return $file->storeAs('evidences/' . $purchaseOrderId, $fileName, self::STORAGE_DISK);
    }

    // Archivos subidos antes de la migración a S3 viven en el disco local por defecto.
    public function resolveDisk(): ?\Illuminate\Contracts\Filesystem\Filesystem
    {
        if (!$this->file_path) {
            return null;
        }

        foreach (array_unique([self::STORAGE_DISK, config('filesystems.default')]) as $name) {
            if (Storage::disk($name)->exists($this->file_path)) {
                return Storage::disk($name);
            }
        }

        return null;
    }

    public function deleteFile(): void
    {
        if (!$this->file_path) {
            return;
        }

        foreach (array_unique([self::STORAGE_DISK, config('filesystems.default')]) as $name) {
            Storage::disk($name)->delete($this->file_path);
        }
    }

    public function streamFile(string $disposition = 'inline'): ?StreamedResponse
    {
        $disk = $this->resolveDisk();

        if (!$disk) {
            return null;
        }

        $downloadName = str_replace('"', '', (string) ($this->file_name ?: basename($this->file_path)));

        return $disk->response($this->file_path, $downloadName, [
            'Content-Type' => $this->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
        ]);
    }
}
