@extends('layouts.app')

@section('page_title', 'Estado de Cuenta')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.dashboard') }}">Portal</a></li>
    <li class="breadcrumb-item active">Estado de Cuenta</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h5 class="card-title mb-0">
            <i class="ri-file-list-3-line me-1"></i> Estado de Cuenta de Facturas
        </h5>
        <span class="badge bg-secondary-subtle text-secondary">{{ $invoices->total() }} registro(s)</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Estatus</th>
                        <th>Fecha de carga</th>
                        <th>Fecha de emision</th>
                        <th>Fecha de vencimiento</th>
                        <th>Folio factura</th>
                        <th>Orden de compra</th>
                        <th>Comprador</th>
                        <th>Estatus de pago</th>
                        <th class="text-end">Total (alcance liquido)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @php
                            $statusMap = [
                                'en_proceso' => ['label' => 'En Proceso', 'class' => 'bg-warning-subtle text-warning'],
                                'aceptada' => ['label' => 'Aceptada', 'class' => 'bg-success-subtle text-success'],
                                'rechazada' => ['label' => 'Rechazada', 'class' => 'bg-danger-subtle text-danger'],
                            ];
                            $statusMeta = $statusMap[$invoice->status] ?? ['label' => 'En Proceso', 'class' => 'bg-warning-subtle text-warning'];
                            $netScope = (float) ($invoice->net_scope ?? $invoice->amount ?? 0);
                            $currency = $invoice->currency ?: 'MXN';
                            $paymentStatusMap = [
                                'por_autorizar' => ['label' => 'Por autorizar', 'class' => 'bg-warning-subtle text-warning'],
                                'autorizado' => ['label' => 'Autorizado', 'class' => 'bg-info-subtle text-info'],
                                'pagado' => ['label' => 'Pagado', 'class' => 'bg-success-subtle text-success'],
                                'rechazado' => ['label' => 'Rechazado', 'class' => 'bg-danger-subtle text-danger'],
                                'pospuesto' => ['label' => 'Pospuesto', 'class' => 'bg-secondary-subtle text-secondary'],
                            ];
                            $linkedPaymentStatuses = $invoice->milestones
                                ->map(fn ($milestone) => $milestone->payments->first()?->status)
                                ->filter()
                                ->unique()
                                ->values();
                        @endphp
                        <tr>
                            <td>
                                <span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                            </td>
                            <td>{{ optional($invoice->attached_at ?? $invoice->created_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ optional($invoice->issue_date)->format('d/m/Y') ?: '—' }}</td>
                            <td>{{ optional($invoice->due_date)->format('d/m/Y') ?: '—' }}</td>
                            <td class="fw-medium">{{ $invoice->folio ?: ('FACT-' . $invoice->id) }}</td>
                            <td>
                                @if ($invoice->purchaseOrder)
                                    <a href="{{ route('supplier_portal.purchase_orders.preview', $invoice->purchaseOrder) }}" target="_blank" class="text-decoration-none">
                                        {{ $invoice->purchaseOrder->folio ?: ('OC #' . $invoice->purchaseOrder->id) }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $invoice->purchaseOrder->elaborated_by ?? '—' }}</td>
                            <td>
                                @if ($invoice->milestones->isEmpty())
                                    <span class="text-muted">Sin hito vinculado</span>
                                @elseif ($linkedPaymentStatuses->isEmpty())
                                    <span class="text-muted">Sin pago registrado</span>
                                @else
                                    @foreach ($linkedPaymentStatuses as $paymentStatus)
                                        @php($paymentStatusMeta = $paymentStatusMap[$paymentStatus] ?? ['label' => ucfirst($paymentStatus), 'class' => 'bg-secondary-subtle text-secondary'])
                                        <span class="badge {{ $paymentStatusMeta['class'] }}">{{ $paymentStatusMeta['label'] }}</span>
                                    @endforeach
                                @endif
                            </td>
                            <td class="text-end fw-semibold">{{ $currency }} {{ number_format($netScope, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No hay facturas registradas para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($invoices->hasPages())
        <div class="card-footer d-flex justify-content-end">
            {{ $invoices->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
