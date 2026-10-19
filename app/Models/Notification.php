<?php

namespace App\Models;

use App\Models\NotificationRecipient as NotificationRecipientModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    public const MODULE_LABELS = [
        'Supplier' => ['label' => 'Proveedor', 'color' => 'primary'],
        'SupplierContact' => ['label' => 'Contacto de proveedor', 'color' => 'primary'],
        'SupplierLocation' => ['label' => 'Ubicación de proveedor', 'color' => 'primary'],
        'PurchaseOrder' => ['label' => 'Orden de compra', 'color' => 'info'],
        'PurchaseOrderEvidence' => ['label' => 'Evidencia de OC', 'color' => 'info'],
        'PurchaseOrderInvoice' => ['label' => 'Factura de OC', 'color' => 'info'],
        'PurchaseOrderMilestone' => ['label' => 'Hito de OC', 'color' => 'info'],
        'purchase_request' => ['label' => 'Solicitud de compra', 'color' => 'info'],
        'material_request' => ['label' => 'Solicitud de material', 'color' => 'info'],
        'material_request_commitment' => ['label' => 'Compromiso de solicitud', 'color' => 'info'],
        'MaterialVoucher' => ['label' => 'Vale de material', 'color' => 'info'],
        'Payment' => ['label' => 'Pago', 'color' => 'success'],
        'MobileAsset' => ['label' => 'Activo móvil', 'color' => 'secondary'],
        'Project' => ['label' => 'Proyecto', 'color' => 'dark'],
        'ProjectAgreement' => ['label' => 'Convenio de proyecto', 'color' => 'dark'],
        'ProjectEstimate' => ['label' => 'Estimación de proyecto', 'color' => 'dark'],
        'ProjectWork' => ['label' => 'Obra de proyecto', 'color' => 'dark'],
        'StockEntry' => ['label' => 'Entrada de inventario', 'color' => 'secondary'],
        'StockExit' => ['label' => 'Salida de inventario', 'color' => 'secondary'],
        'Tool' => ['label' => 'Herramienta', 'color' => 'secondary'],
        'ToolControl' => ['label' => 'Control de herramienta', 'color' => 'secondary'],
        'Worker' => ['label' => 'Trabajador', 'color' => 'warning'],
        'WorkerAttendance' => ['label' => 'Asistencia', 'color' => 'warning'],
        'WorkerDc3' => ['label' => 'DC-3 de trabajador', 'color' => 'warning'],
        'WorkerFile' => ['label' => 'Expediente de trabajador', 'color' => 'warning'],
        'WorkerGroup' => ['label' => 'Grupo de trabajadores', 'color' => 'warning'],
        'WorkerTermination' => ['label' => 'Baja de trabajador', 'color' => 'warning'],
        'WorkerVacation' => ['label' => 'Vacaciones', 'color' => 'warning'],
        'Incentive' => ['label' => 'Incentivo', 'color' => 'success'],
        'PayrollLine' => ['label' => 'Línea de nómina', 'color' => 'success'],
        'PayrollPeriod' => ['label' => 'Periodo de nómina', 'color' => 'success'],
        'PieceworkWeeklyEntry' => ['label' => 'Destajo semanal', 'color' => 'success'],
        'PositionCategory' => ['label' => 'Categoría de puesto', 'color' => 'secondary'],
        'ConceptCategory' => ['label' => 'Categoría de concepto', 'color' => 'secondary'],
        'invoice' => ['label' => 'Factura', 'color' => 'info'],
        'payment' => ['label' => 'Pago', 'color' => 'success'],
    ];

    public const ACTION_LABELS = [
        'create' => ['icon' => 'ri-add-circle-line', 'color' => 'success', 'label' => 'Creación'],
        'update' => ['icon' => 'ri-edit-line', 'color' => 'warning', 'label' => 'Actualización'],
        'delete' => ['icon' => 'ri-delete-bin-line', 'color' => 'danger', 'label' => 'Eliminación'],
        'destroy' => ['icon' => 'ri-delete-bin-line', 'color' => 'danger', 'label' => 'Eliminación'],
        'force_destroy' => ['icon' => 'ri-delete-bin-2-line', 'color' => 'danger', 'label' => 'Eliminación definitiva'],
        'archive' => ['icon' => 'ri-archive-line', 'color' => 'secondary', 'label' => 'Archivado'],
        'unarchive' => ['icon' => 'ri-inbox-unarchive-line', 'color' => 'info', 'label' => 'Desarchivado'],
        'restore' => ['icon' => 'ri-arrow-go-back-line', 'color' => 'info', 'label' => 'Restauración'],
        'request_reactivation' => ['icon' => 'ri-refresh-line', 'color' => 'info', 'label' => 'Solicitud de reactivación'],
    ];

    protected $fillable = [
        'action_by',
        'model_action',
        'model_id',
        'type',
        'data',
        'read_at',
        'is_hidden',
    ];

    public function scopeForPurchaseOrder(Builder $query, PurchaseOrder $purchaseOrder): Builder
    {
        $orderId = $purchaseOrder->id;
        $milestoneIds = $purchaseOrder->milestones()->pluck('id');
        $paymentIds = $purchaseOrder->payments()->pluck('payments.id');
        $invoiceIds = $purchaseOrder->invoices()->pluck('id');
        $evidenceIds = $purchaseOrder->evidences()->withoutGlobalScopes()->pluck('id');

        // Los registros eliminados ya no existen, por lo que se identifican por el texto guardado.
        $deletedLike = fn ($suffix) => ["%orden de compra #{$orderId}{$suffix}", "%OC #{$orderId}{$suffix}"];

        return $query->where(function (Builder $q) use ($orderId, $milestoneIds, $paymentIds, $invoiceIds, $evidenceIds, $deletedLike) {
            $q->where(fn (Builder $sub) => $sub->where('type', 'PurchaseOrder')->where('model_id', (string) $orderId))
                ->orWhere(fn (Builder $sub) => $sub->where('type', 'PurchaseOrderMilestone')->whereIn('model_id', $milestoneIds->map(fn ($id) => (string) $id)))
                ->orWhere(fn (Builder $sub) => $sub->where('type', 'Payment')->whereIn('model_id', $paymentIds->map(fn ($id) => (string) $id)))
                ->orWhere(fn (Builder $sub) => $sub->where('type', 'PurchaseOrderInvoice')->whereIn('model_id', $invoiceIds->map(fn ($id) => (string) $id)))
                ->orWhere(fn (Builder $sub) => $sub->where('type', 'PurchaseOrderEvidence')->whereIn('model_id', $evidenceIds->map(fn ($id) => (string) $id)));

            foreach (['PurchaseOrderMilestone', 'Payment', 'PurchaseOrderInvoice'] as $type) {
                foreach ($deletedLike('') as $pattern) {
                    $q->orWhere(fn (Builder $sub) => $sub->where('type', $type)->where('data', 'like', $pattern));
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipientModel::class);
    }
}
