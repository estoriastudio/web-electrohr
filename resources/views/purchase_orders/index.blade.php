@extends('layouts.app')

@section('page_title', 'Órdenes de Compra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
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

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-xl-12">
        @hasanyrole('admin|Pagos|Orden de compra')
        <div class="card mb-3">
            <button type="button"
                    class="card-header d-flex justify-content-between align-items-center border-bottom recent-invoices-header {{ $recentPendingInvoices->isNotEmpty() ? 'recent-invoices-has-pending' : '' }}"
                    data-bs-toggle="collapse" data-bs-target="#recentPendingInvoicesCollapse" aria-expanded="false"
                    aria-controls="recentPendingInvoicesCollapse">
                <span class="card-title h4 mb-0 text-start">
                    <span class="recent-invoices-activity text-warning {{ $recentPendingInvoices->isNotEmpty() ? 'recent-invoices-pulse' : '' }}">
                        <i class="ri-file-list-3-line"></i>
                    </span>
                    Hay nuevas facturas en tus Órdenes de compra
                </span>
                <span class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning-subtle text-warning fs-6 px-3 py-2">{{ $recentPendingInvoices->count() }} pendiente(s)</span>
                    <i class="ri-arrow-down-s-line recent-invoices-toggle" aria-hidden="true"></i>
                </span>
            </button>
            <div class="collapse" id="recentPendingInvoicesCollapse">
                <div class="card-body p-0">
                    @if ($recentPendingInvoices->isEmpty())
                        <div class="text-center text-muted py-4">No hay facturas pendientes de validación.</div>
                    @else
                        @php
                            $invoiceStatusMap = [
                                'en_proceso' => ['label' => 'En Proceso', 'class' => 'bg-warning-subtle text-warning'],
                                'aceptada' => ['label' => 'Aceptada', 'class' => 'bg-success-subtle text-success'],
                                'rechazada' => ['label' => 'Rechazada', 'class' => 'bg-danger-subtle text-danger'],
                            ];
                        @endphp
                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th>Estatus</th>
                                        <th>Folio</th>
                                        <th>Orden de compra</th>
                                        <th>Proveedor</th>
                                        <th>Comprador</th>
                                        <th>Fecha de carga</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentPendingInvoices as $invoice)
                                        @php
                                            $purchaseOrder = $invoice->purchaseOrder;
                                            $supplierName = $purchaseOrder?->supplier?->commercial_name ?: $purchaseOrder?->supplier?->rfc_name;
                                            $statusMeta = $invoiceStatusMap[$invoice->status] ?? $invoiceStatusMap['en_proceso'];
                                        @endphp
                                        <tr>
                                            <td><span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span></td>
                                            <td class="fw-medium">{{ $invoice->folio ?: ('FACT-' . $invoice->id) }}</td>
                                            <td>
                                                @if ($purchaseOrder)
                                                    <a href="{{ route('purchase_orders.show', $purchaseOrder) }}" class="text-decoration-none">
                                                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12 font-monospace">
                                                            #{{ $purchaseOrder->folio ?? $purchaseOrder->id }}
                                                        </span>
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $supplierName ?: '—' }}</td>
                                            <td>{{ $purchaseOrder?->elaborated_by ?: '—' }}</td>
                                            <td>{{ optional($invoice->attached_at)->format('d/m/Y H:i') ?: '—' }}</td>
                                            <td>
                                                <a href="{{ route('invoices.index', ['section' => 'en_proceso', 'search' => $invoice->folio ?: ('FACT-' . $invoice->id)]) }}"
                                                   class="btn btn-sm btn-warning"
                                                   title="Revisar factura">
                                                    <i class="ri-shield-check-line me-1"></i> Revisar
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <a href="{{ route('invoices.index', ['section' => 'en_proceso']) }}" class="btn btn-sm btn-primary">
                        Ver todas las facturas
                    </a>
                </div>
            </div>
        </div>
        @endhasanyrole

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Listado de órdenes de compra</h4>
                </div>
                <div>
                    @hasanyrole('admin|Orden de compra')
                    @can('create')
                    <a href="{{ route('purchase_orders.create') }}" class="btn btn-sm btn-primary">
                        <i class="ri-add-line me-1"></i> Nueva orden de compra
                    </a>
                    @endcan
                    @endhasanyrole
                    <a href="{{ route('concepts.awarded_prices') }}" class="btn btn-sm btn-soft-info ms-1" title="Precios adjudicados">
                        <i class="ri-price-tag-3-line me-1"></i> Precios adjudicados
                    </a>
                    @hasanyrole('admin|Pagos|Orden de compra')
                    <a href="{{ route('purchase_orders.archived') }}" class="btn btn-sm btn-outline-secondary ms-1" title="Ver archivadas">
                        <i class="ri-archive-line me-1"></i> Archivadas
                    </a>
                    @endhasanyrole
                    @hasrole('admin')
                    <a href="{{ route('purchase_orders.soft_deleted') }}" class="btn btn-sm btn-outline-danger ms-1" title="Papelera">
                        <i class="ri-delete-bin-line me-1"></i> Papelera
                    </a>
                    @endhasrole
                </div>
            </div>

            {{-- Barra de filtros --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('purchase_orders.index') }}" class="row g-2 align-items-end">
                    {{-- Búsqueda por proveedor --}}
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por proveedor…"
                                   autocomplete="off">
                        </div>
                    </div>

                    {{-- Filtro por tipo --}}
                    <div class="col-md-3">
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">Todos los tipos</option>
                            <option value="materiales_servicios" {{ $tipo === 'materiales_servicios' ? 'selected' : '' }}>Materiales / Servicios</option>
                            <option value="mantenimiento" {{ $tipo === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                        </select>
                    </div>

                    {{-- Ordenar por vencimiento --}}
                    <div class="col-md-2">
                        <select name="sort_due" class="form-select form-select-sm">
                            <option value="">Más recientes primero</option>
                            <option value="asc" {{ $sortDue === 'asc' ? 'selected' : '' }}>Vence próximo primero</option>
                            <option value="desc" {{ $sortDue === 'desc' ? 'selected' : '' }}>Vence más tarde primero</option>
                        </select>
                    </div>

                    {{-- Acciones --}}
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search || $tipo || $sortDue)
                            <a href="{{ route('purchase_orders.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @include('purchase_orders.utilities._table')
            </div>

            @if ($orders->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@push('styles')
<style>
.recent-invoices-header {
    background-color: var(--bs-card-cap-bg);
    border: 0;
    color: inherit;
    cursor: pointer;
    text-align: inherit;
    width: 100%;
}

.recent-invoices-header.recent-invoices-has-pending[aria-expanded="false"] {
    background-color: var(--bs-warning-bg-subtle);
}

.recent-invoices-activity {
    display: inline-grid;
    height: 1.35em;
    place-items: center;
    position: relative;
    vertical-align: -0.2em;
    width: 1.35em;
}

.recent-invoices-activity i {
    position: relative;
    z-index: 1;
}

.recent-invoices-pulse i {
    animation: recent-invoices-pulse 1.5s ease-in-out infinite;
}

.recent-invoices-pulse::before,
.recent-invoices-pulse::after {
    animation: recent-invoices-wave 1.8s ease-out infinite;
    border: 2px solid currentColor;
    border-radius: 50%;
    content: '';
    inset: 0;
    opacity: 0;
    position: absolute;
}

.recent-invoices-pulse::after {
    animation-delay: 0.6s;
}

.recent-invoices-toggle {
    display: inline-block;
    transition: transform 0.2s ease;
}

.recent-invoices-header[aria-expanded="true"] .recent-invoices-toggle {
    transform: rotate(180deg);
}

@keyframes recent-invoices-pulse {
    50% {
        opacity: 0.55;
        transform: scale(1.2);
    }
}

@keyframes recent-invoices-wave {
    0% {
        opacity: 0.8;
        transform: scale(0.6);
    }

    100% {
        opacity: 0;
        transform: scale(2.2);
    }
}

@media (prefers-reduced-motion: reduce) {
    .recent-invoices-pulse,
    .recent-invoices-pulse i,
    .recent-invoices-pulse::before,
    .recent-invoices-pulse::after,
    .recent-invoices-toggle {
        animation: none;
        transition: none;
    }
}
</style>
@endpush

@endsection

