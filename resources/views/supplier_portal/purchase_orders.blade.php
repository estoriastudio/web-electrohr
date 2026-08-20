@extends('layouts.app')

@section('page_title', 'Mis Órdenes de Compra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.dashboard') }}">Portal</a></li>
    <li class="breadcrumb-item active">Órdenes de Compra</li>
@endsection

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@push('styles')
<style>
    .oc-collapse-col {
        width: 58px;
        text-align: center;
    }
    .oc-collapse-btn {
        border: 1px solid var(--bs-border-color);
        background: var(--bs-light);
        color: var(--bs-dark);
        border-radius: .5rem;
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all .18s ease;
    }
    .oc-collapse-btn:hover {
        border-color: rgba(var(--bs-primary-rgb), .5);
        background: rgba(var(--bs-primary-rgb), .08);
        color: var(--bs-primary);
    }
    .oc-collapse-btn:focus-visible {
        outline: 0;
        box-shadow: 0 0 0 .22rem rgba(var(--bs-primary-rgb), .22);
    }
    .oc-row-chevron {
        transition: transform .2s ease;
    }
    .oc-collapse-btn[aria-expanded="true"] .oc-row-chevron {
        transform: rotate(180deg);
    }
    .oc-folio-badge {
        font-size: .8rem;
        font-weight: 700;
        letter-spacing: .02em;
        border: 1px solid rgba(var(--bs-primary-rgb), .25);
    }
    .oc-collapse-wrap {
        background: var(--bs-body-bg);
        border-top: 1px dashed var(--bs-border-color);
    }
    .oc-preview-frame {
        width: 100%;
        height: 74vh;
        border: 0;
        background: #fff;
    }
    .oc-invoice-actions .btn {
        min-width: 34px;
    }
</style>
@endpush

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h4 class="card-title mb-0">Mis órdenes de compra</h4>
    </div>

    <div class="card-body border-bottom py-3">
        <form method="GET" action="{{ route('supplier_portal.purchase_orders.index') }}" class="d-flex gap-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Buscar por folio, proyecto o sitio..." autocomplete="off">
                @if ($search)
                    <a href="{{ route('supplier_portal.purchase_orders.index') }}" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                        <i class="ri-close-line"></i>
                    </a>
                @endif
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                <thead class="bg-light-subtle">
                    <tr>
                        <th class="oc-collapse-col text-center" title="Mostrar desglose">Ver</th>
                        <th>Orden de compra</th>
                        <th>Proyecto / obra</th>
                        <th>Moneda</th>
                        <th>Importe total</th>
                        <th>Importe registrado</th>
                        <th>Importe pendiente</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrders as $order)
                        @php
                            $reservedInvoices = $order->invoices->whereIn('status', ['en_proceso', 'aceptada']);
                            $invoicedAmount = (float) $reservedInvoices->sum(function ($invoice) {
                                return (float) ($invoice->net_scope ?? $invoice->amount ?? 0);
                            });
                            $pendingAmount = max(0, (float) $order->amount - $invoicedAmount);
                            $projectName = $order->projectRelation?->name ?? $order->project;
                            $workName = $order->workRelation?->name ?? $order->site;
                            $collapseId = 'portal-order-invoices-' . $order->id;
                        @endphp
                        <tr>
                            <td class="oc-collapse-col">
                                <button type="button"
                                        class="oc-collapse-btn"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $collapseId }}"
                                        aria-expanded="false"
                                        aria-controls="{{ $collapseId }}"
                                        title="Mostrar/Ocultar facturas">
                                    <i class="ri-arrow-down-s-line oc-row-chevron"></i>
                                </button>
                            </td>
                            <td>
                                <a href="#"
                                   class="fw-medium text-decoration-none js-oc-preview"
                                   data-preview-url="{{ route('supplier_portal.purchase_orders.preview', $order) }}"
                                   title="Ver vista previa de OC">
                                    <span class="badge bg-primary-subtle text-primary oc-folio-badge">
                                        OC {{ $order->folio }}
                                    </span>
                                </a>
                            </td>
                            <td>
                                <div class="hover-marquee" style="--marquee-width:150px;" title="{{ $projectName ?: 'Sin proyecto' }}">
                                    <span class="track"><span>{{ $projectName ?: 'Sin proyecto' }}</span><span aria-hidden="true">{{ $projectName ?: 'Sin proyecto' }}</span></span>
                                </div>
                                @if ($workName)
                                    <small class="text-muted d-block hover-marquee" style="--marquee-width:150px;" title="{{ $workName }}">
                                        <span class="track"><span>{{ $workName }}</span><span aria-hidden="true">{{ $workName }}</span></span>
                                    </small>
                                @endif
                            </td>
                            <td>{{ $order->currency }}</td>
                            <td class="fw-medium">${{ number_format($order->amount, 2) }}</td>
                            <td class="text-success fw-medium">${{ number_format($invoicedAmount, 2) }}</td>
                            <td class="text-warning fw-medium">${{ number_format($pendingAmount, 2) }}</td>
                            <td>
                                @if ($pendingAmount > 0)
                                    <a href="{{ route('supplier_portal.invoices.create', $order) }}" class="btn btn-sm btn-primary js-oc-row-ignore">
                                        <i class="ri-upload-2-line me-1"></i> Subir factura
                                    </a>
                                @else
                                    <button type="button" class="btn btn-sm btn-light" disabled>
                                        <i class="ri-checkbox-circle-line me-1"></i> Facturada por completo
                                    </button>
                                @endif
                            </td>
                        </tr>
                        <tr class="bg-light-subtle">
                            <td colspan="8" class="p-0 border-0">
                                <div id="{{ $collapseId }}" class="collapse oc-collapse-wrap">
                                    <div class="p-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <h6 class="mb-0">
                                                <i class="ri-file-list-3-line me-1 text-primary"></i>
                                                Desglose de facturas
                                                <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $order->invoices->count() }}</span>
                                            </h6>
                                            <span class="text-muted fs-12">Total registrado (aceptadas y en proceso): <strong>{{ $order->currency }} {{ number_format((float) $reservedInvoices->sum(function ($invoice) { return (float) ($invoice->net_scope ?? $invoice->amount ?? 0); }), 2) }}</strong></span>
                                        </div>

                                        @if ($order->invoices->isNotEmpty())
                                            <div class="table-responsive">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Estatus</th>
                                                            <th>Folio</th>
                                                            <th>Fecha</th>
                                                            <th>Vencimiento</th>
                                                            <th class="text-end">Importe</th>
                                                            <th class="text-end">Nota credito</th>
                                                            <th class="text-end">Alcance liquido</th>
                                                            <th>PDF</th>
                                                            <th>XML</th>
                                                            <th>NC PDF</th>
                                                            <th>NC XML</th>
                                                            <th>Evidencia</th>
                                                            <th>Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($order->invoices as $invoice)
                                                            @php
                                                                $statusMap = [
                                                                    'en_proceso' => ['label' => 'En Proceso', 'class' => 'bg-warning-subtle text-warning'],
                                                                    'aceptada' => ['label' => 'Aceptada', 'class' => 'bg-success-subtle text-success'],
                                                                    'rechazada' => ['label' => 'Rechazada', 'class' => 'bg-danger-subtle text-danger'],
                                                                ];
                                                                $statusMeta = $statusMap[$invoice->status] ?? $statusMap['en_proceso'];
                                                            @endphp
                                                            <tr>
                                                                <td><span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span></td>
                                                                <td class="fw-medium">{{ $invoice->folio ?: ('FACT-' . $invoice->id) }}</td>
                                                                <td>{{ optional($invoice->attached_at ?? $invoice->created_at)->format('d/m/Y H:i') }}</td>
                                                                <td>{{ optional($invoice->due_date)->format('d/m/Y') ?: '—' }}</td>
                                                                <td class="text-end">{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td>
                                                                <td class="text-end">{{ $invoice->credit_note_amount !== null ? ($invoice->currency . ' ' . number_format((float) $invoice->credit_note_amount, 2)) : '—' }}</td>
                                                                <td class="text-end fw-medium">{{ $invoice->currency }} {{ number_format((float) ($invoice->net_scope ?? $invoice->amount), 2) }}</td>
                                                                <td>
                                                                    @if ($invoice->file_path)
                                                                        <a href="{{ route('supplier_portal.invoices.download_file', ['purchaseOrder' => $order, 'invoice' => $invoice, 'type' => 'pdf']) }}"
                                                                           target="_blank"
                                                                           class="btn btn-xs btn-soft-primary oc-invoice-actions js-oc-row-ignore"
                                                                           style="padding: 2px 8px;"
                                                                           title="Ver PDF de factura">
                                                                            <i class="ri-file-pdf-line"></i>
                                                                        </a>
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if ($invoice->xml_file_path)
                                                                        <a href="{{ route('supplier_portal.invoices.download_file', ['purchaseOrder' => $order, 'invoice' => $invoice, 'type' => 'xml']) }}"
                                                                           target="_blank"
                                                                           class="btn btn-xs btn-soft-info oc-invoice-actions js-oc-row-ignore"
                                                                           style="padding: 2px 8px;"
                                                                           title="Ver XML de factura">
                                                                            <i class="ri-code-s-slash-line"></i>
                                                                        </a>
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if ($invoice->credit_note_file_path)
                                                                        <a href="{{ route('supplier_portal.invoices.download_file', ['purchaseOrder' => $order, 'invoice' => $invoice, 'type' => 'credit_note_pdf']) }}"
                                                                           target="_blank"
                                                                           class="btn btn-xs btn-soft-warning oc-invoice-actions js-oc-row-ignore"
                                                                           style="padding: 2px 8px;"
                                                                           title="Ver PDF de nota de credito">
                                                                            <i class="ri-file-warning-line"></i>
                                                                        </a>
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if ($invoice->credit_note_xml_file_path)
                                                                        <a href="{{ route('supplier_portal.invoices.download_file', ['purchaseOrder' => $order, 'invoice' => $invoice, 'type' => 'credit_note_xml']) }}"
                                                                           target="_blank"
                                                                           class="btn btn-xs btn-soft-warning oc-invoice-actions js-oc-row-ignore"
                                                                           style="padding: 2px 8px;"
                                                                           title="Ver XML de nota de credito">
                                                                            <i class="ri-code-s-slash-line"></i>
                                                                        </a>
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if ($invoice->evidence_file_path)
                                                                        <a href="{{ route('supplier_portal.invoices.download_file', ['purchaseOrder' => $order, 'invoice' => $invoice, 'type' => 'evidence']) }}"
                                                                           target="_blank"
                                                                           class="btn btn-xs btn-soft-success oc-invoice-actions js-oc-row-ignore"
                                                                           style="padding: 2px 8px;"
                                                                           title="Ver evidencia">
                                                                            <i class="ri-archive-line"></i>
                                                                        </a>
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    <a href="{{ route('supplier_portal.invoices.credit_note.edit', ['purchaseOrder' => $order, 'invoice' => $invoice]) }}"
                                                                       class="btn btn-xs btn-soft-warning js-oc-row-ignore"
                                                                       style="padding: 2px 8px;"
                                                                       title="{{ $invoice->credit_note_file_path ? 'Editar nota de crédito' : 'Agregar nota de crédito' }}">
                                                                        <i class="ri-edit-2-line"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @else
                                            <div class="text-center text-muted py-3">
                                                No hay facturas registradas para esta orden.
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay órdenes de compra autorizadas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($purchaseOrders->hasPages())
        <div class="card-footer d-flex justify-content-end">
            {{ $purchaseOrders->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>

<div class="modal fade" id="modalPortalOcPreview" tabindex="-1" aria-labelledby="modalPortalOcPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPortalOcPreviewLabel">
                    <i class="ri-file-paper-2-line me-1"></i> Vista previa de Orden de Compra
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <iframe id="portalOcPreviewFrame" class="oc-preview-frame" src="about:blank" title="Vista previa de Orden de Compra"></iframe>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('modalPortalOcPreview');
    var previewFrame = document.getElementById('portalOcPreviewFrame');

    document.querySelectorAll('.js-oc-row-ignore').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    });

    document.querySelectorAll('.js-oc-preview').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var url = link.getAttribute('data-preview-url');
            if (!url || !modalEl || !previewFrame) return;
            previewFrame.setAttribute('src', url);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (previewFrame) previewFrame.setAttribute('src', 'about:blank');
        });
    }
});
</script>
@endpush
