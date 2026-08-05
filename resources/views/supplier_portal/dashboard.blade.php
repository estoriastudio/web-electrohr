@extends('layouts.app')

@push('styles')
    <style>
        .welcome-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.5rem;
            background-image:
                linear-gradient(120deg, rgba(10, 35, 66, 0.9) 0%, rgba(10, 35, 66, 0.62) 48%, rgba(10, 35, 66, 0.2) 100%),
                var(--welcome-hero-banner),
                url('{{ asset('welcome/welcome-default.jpg') }}');
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            box-shadow: 0 1.5rem 3rem rgba(15, 23, 42, 0.12);
        }

        .welcome-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.18), transparent 36%);
            pointer-events: none;
        }

        .welcome-hero__content {
            position: relative;
            z-index: 1;
            max-width: 46rem;
            padding: 52px 0;
        }
    </style>
@endpush

@section('page_title', 'Portal de Proveedores')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.dashboard') }}">Portal</a></li>
    <li class="breadcrumb-item active">Inicio</li>
@endsection

@section('content')
@php
    $welcomeTitle = 'Bienvenido al Portal de Proveedores SAHR 2.0.';
    $welcomeDescription = 'Desde este portal puedes revisar tus órdenes de compra y subir la documentación requerida para tus facturas. El equipo de ElectroHR dará seguimiento a cada entrega.';
@endphp

<div class="welcome-hero text-white p-4 p-lg-5 mb-4"
     style="--welcome-hero-banner: url('{{ asset('welcome/welcome-supplier.jpg') }}');">
    <div class="welcome-hero__content">
        <span class="badge text-bg-light text-dark rounded-pill px-3 py-2 mb-3">SAHR 2.0</span>
        <h1 class="display-6 fw-bold mb-1">Hola <span class="footer-text">{{ Auth::user()->name }}</span>. <br>{{ $welcomeTitle }}</h1>
        <p class="lead mb-0">{{ $welcomeDescription }}</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card border-primary-subtle">
            <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <h5 class="mb-1">Descarga instructivo</h5>
                    <p class="text-muted mb-0">
                        Consulta la guía oficial para cargar facturas en el portal de forma correcta.
                    </p>
                </div>
                <a href="{{ route('supplier_portal.invoice_guide.download') }}" class="btn btn-primary">
                    <i class="ri-download-2-line me-1"></i> Descargar PDF
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h5 class="card-title mb-0">Centro de Notificaciones</h5>
                    <p class="text-muted fs-13 mb-0">Seguimiento del estatus de tus facturas (aprobadas, rechazadas y en proceso).</p>
                </div>
                <a href="{{ route('supplier_portal.account_statement.index') }}" class="btn btn-sm btn-light">Ver estado de cuenta</a>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-warning-subtle text-warning py-2 px-3">
                        En proceso: {{ $invoiceStatusSummary['en_proceso'] ?? 0 }}
                    </span>
                    <span class="badge bg-success-subtle text-success py-2 px-3">
                        Aprobadas: {{ $invoiceStatusSummary['aceptada'] ?? 0 }}
                    </span>
                    <span class="badge bg-danger-subtle text-danger py-2 px-3">
                        Rechazadas: {{ $invoiceStatusSummary['rechazada'] ?? 0 }}
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Factura</th>
                                <th>OC</th>
                                <th>Monto</th>
                                <th>Estatus</th>
                                <th>Actualización</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentInvoiceNotifications as $invoice)
                                <tr>
                                    <td>{{ $invoice->folio ?: ('#' . $invoice->id) }}</td>
                                    <td>{{ $invoice->purchaseOrder?->folio ?: '—' }}</td>
                                    <td>{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td>
                                    <td>
                                        @if($invoice->status === 'aceptada')
                                            <span class="badge bg-success-subtle text-success">Aprobada</span>
                                        @elseif($invoice->status === 'rechazada')
                                            <span class="badge bg-danger-subtle text-danger">Rechazada</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning">En proceso</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($invoice->updated_at)->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Aún no hay notificaciones de facturas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

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
