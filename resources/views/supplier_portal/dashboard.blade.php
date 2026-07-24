@extends('layouts.app')

@section('page_title', 'Portal de Proveedores')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.dashboard') }}">Portal</a></li>
    <li class="breadcrumb-item active">Inicio</li>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted fs-12 mb-1">Órdenes de compra</div>
                <h3 class="mb-0">{{ $purchaseOrdersCount }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted fs-12 mb-1">Vales de material</div>
                <h3 class="mb-0">{{ $materialVouchersCount }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted fs-12 mb-1">Hitos disponibles para facturar</div>
                <h3 class="mb-0">{{ $pendingMilestones }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">Mis órdenes recientes</h5>
                <a href="{{ route('supplier_portal.purchase_orders.index') }}" class="btn btn-sm btn-light">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr>
                                    <td>{{ $order->folio }}</td>
                                    <td>{{ $order->currency }} {{ number_format($order->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-4">Sin órdenes autorizadas registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">Mis vales recientes</h5>
                <a href="{{ route('supplier_portal.material_vouchers.index') }}" class="btn btn-sm btn-light">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentVouchers as $voucher)
                                <tr>
                                    <td>{{ $voucher->folio }}</td>
                                    <td>{{ $voucher->voucher_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ ucfirst($voucher->status) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Sin vales registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
