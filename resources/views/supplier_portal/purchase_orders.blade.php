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
                        <th>Orden de compra</th>
                        <th>Proyecto / obra</th>
                        <th>Moneda</th>
                        <th>Importe total</th>
                        <th>Importe facturado</th>
                        <th>Importe pendiente</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrders as $order)
                        @php
                            $invoicedAmount = (float) ($order->invoiced_amount ?? 0);
                            $pendingAmount = max(0, (float) $order->amount - $invoicedAmount);
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-medium">{{ $order->folio }}</span>
                            </td>
                            <td>
                                @php
                                    $projectName = $order->projectRelation?->name ?? $order->project;
                                    $workName = $order->workRelation?->name ?? $order->site;
                                @endphp
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
                                    <a href="{{ route('supplier_portal.invoices.create', $order) }}" class="btn btn-sm btn-primary">
                                        <i class="ri-upload-2-line me-1"></i> Subir factura
                                    </a>
                                @else
                                    <button type="button" class="btn btn-sm btn-light" disabled>
                                        <i class="ri-checkbox-circle-line me-1"></i> Facturada por completo
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No hay órdenes de compra autorizadas registradas.</td>
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
@endsection
