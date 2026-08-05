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

@section('page_title', 'Bienvenida')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">Inicio</a></li>
    <li class="breadcrumb-item active">Bienvenida</li>
@endsection

@section('content')

@php
    $authUser = auth()->user();
    $primaryRoleSlug = \Illuminate\Support\Str::slug((string) optional($authUser->roles->first())->name ?: 'general');
    $welcomeBannerFile = 'welcome-' . $primaryRoleSlug . '.jpg';
    $welcomeTitle = 'Bienvenido al SAHR 2.0.';
    $welcomeDescription = 'Da seguimiento a tus procesos y usa el tablero para priorizar pendientes del día.';
@endphp

<div class="welcome-hero text-white p-4 p-lg-5 mb-4"
     style="--welcome-hero-banner: url('{{ asset('welcome/' . $welcomeBannerFile) }}');">
    <div class="welcome-hero__content">
        <span class="badge text-bg-light text-dark rounded-pill px-3 py-2 mb-3">SAHR 2.0</span>
        <h1 class="display-6 fw-bold mb-1">Hola <span class="footer-text">{{ Auth::user()->name }}</span>. <br>{{ $welcomeTitle }}</h1>
        <p class="lead mb-0">{{ $welcomeDescription }}</p>
    </div>
</div>

{{-- ============================================================ --}}
{{-- Bloque 1: Indicadores de Pagos — admin + Pagos            --}}
{{-- ============================================================ --}}
@if(auth()->user()->hasAnyRole(['admin', 'Pagos']))
<div class="row">

    {{-- Tarjeta 1: Total acumulado pendiente de pago --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-1 fs-13">Pendiente de Pago</p>
                        <h3 class="mb-0 fw-bold">
                            $ {{ number_format($totalPendientePago, 2) }}
                        </h3>
                        <small class="text-muted">Pagos autorizados sin liquidar</small>
                    </div>
                    <div class="bg-success-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-money-dollar-circle-line fs-24 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tarjeta 2: Total acumulado por autorizar --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-1 fs-13">Por Autorizar</p>
                        <h3 class="mb-0 fw-bold">
                            $ {{ number_format($totalPorAutorizar, 2) }}
                        </h3>
                        <small class="text-muted">Pagos en espera de autorización</small>
                    </div>
                    <div class="bg-warning-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-time-line fs-24 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endif


{{-- ============================================================ --}}
{{-- Bloque 2: Urgencias de OC — admin + orders                   --}}
{{-- ============================================================ --}}
@if(auth()->user()->hasAnyRole(['admin', 'Orden de compra']) && array_sum($urgencyValues) > 0)
<div class="row">

    {{-- Tarjeta 3: Gráfico de urgencias por vencimiento --}}
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Urgencias por Vencimiento de OC</h4>
                    <p class="text-muted fs-13 mb-0">Hitos pendientes de cubrir agrupados por plazo</p>
                </div>
            </div>
            <div class="card-body">
                @if(array_sum($urgencyValues) > 0)
                    <div id="urgencyChart" style="min-height:280px;"></div>
                @else
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted">
                        <i class="ri-bar-chart-2-line fs-1 mb-2"></i>
                        <p class="mb-0">No hay hitos con vencimiento pendiente.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tarjeta 4: Top 5 urgencias más importantes --}}
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Top 5 Hitos más Urgentes</h4>
                    <p class="text-muted fs-13 mb-0">Ordenados por fecha de vencimiento</p>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Proveedor / OC</th>
                                <th>Vencimiento</th>
                                <th>Saldo Pend.</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $today = \Carbon\Carbon::today(); @endphp
                            @forelse ($top5Urgencias as $milestone)
                                @php
                                    $due      = $milestone->due_date;
                                    $isPast   = $due->lt($today);
                                    $daysDiff = (int) $due->diffInDays($today);
                                    $saldo    = $milestone->effective_amount - (float) $milestone->covered_amount;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-medium fs-14">
                                            {{ $milestone->purchaseOrder?->supplier?->name ?? '—' }}
                                        </div>
                                        <small class="text-muted">OC #{{ $milestone->purchase_order_id }}</small>
                                    </td>
                                    <td>{{ $due->format('d/m/Y') }}</td>
                                    <td>$ {{ number_format($saldo, 2) }}</td>
                                    <td>
                                        @if($isPast)
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Vencido</span>
                                        @elseif($daysDiff === 0)
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Hoy</span>
                                        @elseif($daysDiff <= 7)
                                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">{{ $daysDiff }} días</span>
                                        @else
                                            <span class="badge bg-info-subtle text-info py-1 px-2 fs-12">{{ $daysDiff }} días</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No hay urgencias pendientes.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endif


{{-- ============================================================ --}}
{{-- Bloque 3: Pendientes de Autorizar — admin + payments         --}}
{{-- ============================================================ --}}
@if(auth()->user()->hasAnyRole(['admin', 'Pagos']))
<div class="row">

    {{-- Tarjeta 5: Gráfica de pendientes de autorizar --}}
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Pendientes de Autorizar</h4>
                    <p class="text-muted fs-13 mb-0">Monto acumulado por proveedor</p>
                </div>
            </div>
            <div class="card-body">
                @if(count($chartLabels) > 0)
                    <div id="pendingAuthChart" style="min-height:280px;"></div>
                @else
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted">
                        <i class="ri-bar-chart-line fs-1 mb-2"></i>
                        <p class="mb-0">No hay pagos pendientes de autorización.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tarjeta 6: Top 5 pagos pendientes por autorizar más urgentes --}}
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Top 5 Pagos Urgentes por Autorizar</h4>
                    <p class="text-muted fs-13 mb-0">Ordenados por vencimiento del hito</p>
                </div>
                <div>
                    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-arrow-right-line me-1"></i> Ver todos
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Proveedor</th>
                                <th>Monto</th>
                                <th>Vencimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $today2 = \Carbon\Carbon::today(); @endphp
                            @forelse ($top5PorAutorizar as $payment)
                                @php
                                    $msDue = $payment->milestone?->due_date;
                                    if ($msDue) {
                                        $isPastDue = $msDue->lt($today2);
                                        $dDays     = (int) $msDue->diffInDays($today2);
                                    }
                                @endphp
                                <tr>
                                    <td class="fw-medium">{{ $payment->folio }}</td>
                                    <td>
                                        <div class="fw-medium fs-14">
                                            {{ optional($payment->milestone?->purchaseOrder?->supplier)->name ?? '—' }}
                                        </div>
                                        <small class="text-muted">
                                            OC #{{ $payment->milestone?->purchase_order_id ?? '—' }}
                                        </small>
                                    </td>
                                    <td>$ {{ number_format($payment->amount, 2) }}</td>
                                    <td>
                                        @if(!$msDue)
                                            <span class="text-muted">Sin fecha</span>
                                        @elseif($isPastDue)
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Vencido</span>
                                        @elseif($dDays === 0)
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Hoy</span>
                                        @elseif($dDays <= 7)
                                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">{{ $dDays }} días</span>
                                        @else
                                            <span class="badge bg-info-subtle text-info py-1 px-2 fs-12">{{ $dDays }} días</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No hay pagos pendientes de autorización.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endif


{{-- ============================================================ --}}
{{-- Bloque 4: Vista Orden de Compra — admin + Orden de compra    --}}
{{-- ============================================================ --}}
@if(auth()->user()->hasAnyRole(['admin', 'Orden de compra']))

{{-- Fila A: Métricas rápidas --}}
<div class="row">

    {{-- Tarjeta: SOLCOM Pendientes --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-1 fs-13">SOLCOM Pendientes</p>
                        <h3 class="mb-0 fw-bold">{{ $solcomPendientes->count() }}</h3>
                        @php
                            $solcomCambios = $solcomPendientes->where('status', 'changes_requested')->count();
                        @endphp
                        <small class="text-muted">
                            {{ $solcomPendientes->where('status', 'pending')->count() }} por procesar
                            @if($solcomCambios > 0)
                                · <span class="text-danger">{{ $solcomCambios }} con cambios solicitados</span>
                            @endif
                        </small>
                    </div>
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-file-list-3-line fs-24 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tarjeta: OCs Pendientes de Autorizar --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted mb-1 fs-13">OC Pendientes de Autorizar</p>
                        <h3 class="mb-0 fw-bold">{{ $ocsPendientesAutorizar->count() }}</h3>
                        <small class="text-muted">
                            Monto total:
                            $ {{ number_format($ocsPendientesAutorizar->sum('amount'), 2) }}
                        </small>
                    </div>
                    <div class="bg-info-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-checkbox-circle-line fs-24 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Fila B: SOLCOM pendientes + búsqueda --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">SOLCOM Pendientes</h4>
                    <p class="text-muted fs-13 mb-0">Solicitudes en espera de procesar o con cambios solicitados</p>
                </div>
                <div>
                    <a href="{{ route('purchase_requests.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-arrow-right-line me-1"></i> Ver todos
                    </a>
                </div>
            </div>

            {{-- Barra de búsqueda de folios --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('dashboard') }}" class="d-flex gap-2">
                    <div class="input-group input-group-sm" style="max-width:360px;">
                        <span class="input-group-text bg-light">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input type="text" name="solcom_search"
                               value="{{ $solcomSearch }}"
                               class="form-control"
                               placeholder="Buscar por folio SOLCOM…"
                               autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
                    @if($solcomSearch)
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                            <i class="ri-close-line"></i>
                        </a>
                    @endif
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Proyecto</th>
                                <th>Descripción</th>
                                <th>F. Necesidad</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($solcomPendientes->take(10) as $solcom)
                                <tr>
                                    <td class="fw-semibold">#{{ $solcom->folio }}</td>
                                    <td>{{ $solcom->project?->name ?? '—' }}</td>
                                    <td class="text-wrap" style="max-width:220px;">
                                        {{ Str::limit($solcom->short_description ?? '—', 60) }}
                                    </td>
                                    <td>{{ $solcom->need_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td>
                                        @if($solcom->status === 'changes_requested')
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Cambios solicitados</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Pendiente</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('purchase_requests.show', $solcom) }}"
                                           class="btn btn-light btn-sm" title="Ver SOLCOM">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        @if($solcomSearch)
                                            No se encontraron SOLCOM con folio "{{ $solcomSearch }}".
                                        @else
                                            No hay SOLCOM pendientes.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($solcomPendientes->count() > 10)
                    <div class="card-footer text-muted fs-13">
                        Mostrando 10 de {{ $solcomPendientes->count() }} registros.
                        <a href="{{ route('purchase_requests.index') }}">Ver todos</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Fila C: OCs pendientes de autorizar --}}
@if($ocsPendientesAutorizar->count() > 0)
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Órdenes de Compra Pendientes de Autorizar</h4>
                    <p class="text-muted fs-13 mb-0">OCs emitidas en espera de autorización</p>
                </div>
                <a href="{{ route('purchase_orders.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="ri-arrow-right-line me-1"></i> Ver todas
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Proveedor</th>
                                <th>Proyecto / Sitio</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ocsPendientesAutorizar->take(10) as $oc)
                                <tr>
                                    <td class="fw-semibold">#{{ $oc->folio ?? $oc->id }}</td>
                                    <td>{{ $oc->supplier?->name ?? '—' }}</td>
                                    <td>{{ $oc->workRelation?->name ?? $oc->site ?? '—' }}</td>
                                    <td>$ {{ number_format($oc->amount, 2) }}</td>
                                    <td>
                                        @if($oc->status === 'emitida')
                                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Emitida</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Pendiente</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $oc) }}"
                                           class="btn btn-light btn-sm" title="Ver OC">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($ocsPendientesAutorizar->count() > 10)
                    <div class="card-footer text-muted fs-13">
                        Mostrando 10 de {{ $ocsPendientesAutorizar->count() }} registros.
                        <a href="{{ route('purchase_orders.index') }}">Ver todas</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- Fila D: Órdenes pendientes de entregar (por ubicación) --}}
@if($ocsPendientesEntregarSitio->count() > 0 || $ocsPendientesEntregarElectrohr->count() > 0)
<div class="row">

    {{-- Pendientes de entregar — En Sitio --}}
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-bottom">
                <h4 class="card-title mb-0">
                    <i class="ri-map-pin-line me-1 text-primary"></i>Pendientes de Entregar — En Sitio
                </h4>
                <p class="text-muted fs-13 mb-0">
                    {{ $ocsPendientesEntregarSitio->count() }} orden(es) con entrega en obra
                </p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>OC / Proveedor</th>
                                <th>Próx. Entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ocsPendientesEntregarSitio->take(8) as $oc)
                                @php
                                    $nearestDelivery = $oc->items
                                        ->whereNotNull('delivery_date')
                                        ->sortBy('delivery_date')
                                        ->first()?->delivery_date;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-medium fs-14">#{{ $oc->folio ?? $oc->id }}</div>
                                        <small class="text-muted">{{ $oc->supplier?->name ?? '—' }}</small>
                                    </td>
                                    <td>
                                        @if($nearestDelivery)
                                            @php
                                                try {
                                                    $dDate = \Carbon\Carbon::parse($nearestDelivery);
                                                    $isPastDelivery = $dDate->lt(\Carbon\Carbon::today());
                                                } catch (\Exception $e) {
                                                    $dDate = null;
                                                    $isPastDelivery = false;
                                                }
                                            @endphp
                                            @if($dDate)
                                                <span class="{{ $isPastDelivery ? 'text-danger fw-medium' : '' }}">
                                                    {{ $dDate->format('d/m/Y') }}
                                                </span>
                                            @else
                                                <span class="text-muted">{{ $nearestDelivery }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">Sin órdenes pendientes.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Pendientes de entregar — En ElectroHR --}}
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-bottom">
                <h4 class="card-title mb-0">
                    <i class="ri-building-2-line me-1 text-success"></i>Pendientes de Entregar — ElectroHR
                </h4>
                <p class="text-muted fs-13 mb-0">
                    {{ $ocsPendientesEntregarElectrohr->count() }} orden(es) con entrega en oficina
                </p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>OC / Proveedor</th>
                                <th>Próx. Entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ocsPendientesEntregarElectrohr->take(8) as $oc)
                                @php
                                    $nearestDelivery = $oc->items
                                        ->whereNotNull('delivery_date')
                                        ->sortBy('delivery_date')
                                        ->first()?->delivery_date;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-medium fs-14">#{{ $oc->folio ?? $oc->id }}</div>
                                        <small class="text-muted">{{ $oc->supplier?->name ?? '—' }}</small>
                                    </td>
                                    <td>
                                        @if($nearestDelivery)
                                            @php
                                                try {
                                                    $dDate = \Carbon\Carbon::parse($nearestDelivery);
                                                    $isPastDelivery = $dDate->lt(\Carbon\Carbon::today());
                                                } catch (\Exception $e) {
                                                    $dDate = null;
                                                    $isPastDelivery = false;
                                                }
                                            @endphp
                                            @if($dDate)
                                                <span class="{{ $isPastDelivery ? 'text-danger fw-medium' : '' }}">
                                                    {{ $dDate->format('d/m/Y') }}
                                                </span>
                                            @else
                                                <span class="text-muted">{{ $nearestDelivery }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">Sin órdenes pendientes.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endif

{{-- Fila E: OCs vencidas en tiempos de entrega --}}
@if($ocsVencidasEntrega->count() > 0)
<div class="row">
    <div class="col-xl-12">
        <div class="card border-danger-subtle">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom border-danger-subtle">
                <div>
                    <h4 class="card-title mb-0 text-danger">
                        <i class="ri-alarm-warning-line me-1"></i>OCs Vencidas en Tiempos de Entrega
                    </h4>
                    <p class="text-muted fs-13 mb-0">Órdenes autorizadas con fecha de entrega vencida</p>
                </div>
                <span class="badge bg-danger">{{ $ocsVencidasEntrega->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Proveedor</th>
                                <th>Ubicación</th>
                                <th>Fecha Entrega Vencida</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ocsVencidasEntrega as $oc)
                                @php
                                    $oldestOverdue = $oc->items
                                        ->whereNotNull('delivery_date')
                                        ->filter(function ($item) {
                                            try {
                                                return \Carbon\Carbon::parse($item->delivery_date)->lt(\Carbon\Carbon::today());
                                            } catch (\Exception $e) {
                                                return false;
                                            }
                                        })
                                        ->sortBy('delivery_date')
                                        ->first()?->delivery_date;
                                    $locType = $oc->purchaseRequest?->materialRequest?->location_type ?? 'sitio';
                                @endphp
                                <tr>
                                    <td class="fw-semibold">#{{ $oc->folio ?? $oc->id }}</td>
                                    <td>{{ $oc->supplier?->name ?? '—' }}</td>
                                    <td>
                                        @if($locType === 'electrohr')
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">
                                                <i class="ri-building-2-line me-1"></i>ElectroHR
                                            </span>
                                        @else
                                            <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                                <i class="ri-map-pin-line me-1"></i>En Sitio
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($oldestOverdue)
                                            @php
                                                try {
                                                    $overdueDate = \Carbon\Carbon::parse($oldestOverdue);
                                                    $daysLate = (int) $overdueDate->diffInDays(\Carbon\Carbon::today());
                                                } catch (\Exception $e) {
                                                    $overdueDate = null;
                                                    $daysLate = 0;
                                                }
                                            @endphp
                                            @if($overdueDate)
                                                <span class="text-danger fw-medium">
                                                    {{ $overdueDate->format('d/m/Y') }}
                                                </span>
                                                <small class="text-danger ms-1">({{ $daysLate }} días)</small>
                                            @else
                                                <span class="text-danger">{{ $oldestOverdue }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $oc) }}"
                                           class="btn btn-light btn-sm" title="Ver OC">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@endif
{{-- / Bloque 4 --}}

@endsection

@push('scripts')

{{-- Gráfico 3: Urgencias por vencimiento --}}
@if(auth()->user()->hasAnyRole(['admin', 'Orden de compra']) && array_sum($urgencyValues) > 0)
<script>
(function () {
    var opts = {
        series: [{ name: 'Hitos', data: @json($urgencyValues) }],
        chart: {
            type: 'bar',
            height: 280,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                horizontal: true,
                distributed: true,
                borderRadius: 4
            }
        },
        colors: ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#10b981'],
        dataLabels: {
            enabled: true,
            style: { fontSize: '12px', colors: ['#333'] },
            formatter: function (val) { return val > 0 ? val : ''; }
        },
        xaxis: {
            categories: @json($urgencyLabels),
            labels: { style: { fontSize: '12px' } },
            title: { text: 'Número de hitos' }
        },
        legend: { show: false },
        grid: { borderColor: '#f3f4f5', padding: { right: 20 } },
        tooltip: {
            y: { formatter: function (val) { return val + ' hito(s)'; } }
        }
    };
    new ApexCharts(document.querySelector('#urgencyChart'), opts).render();
})();
</script>
@endif

{{-- Gráfico 5: Pendientes de autorizar por proveedor --}}
@if(auth()->user()->hasAnyRole(['admin', 'Pagos']) && count($chartLabels) > 0)
<script>
(function () {
    var opts = {
        series: [{ name: 'Por Autorizar', data: @json($chartValues) }],
        chart: {
            type: 'bar',
            height: 280,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: { borderRadius: 4, columnWidth: '55%' }
        },
        colors: ['#f59e0b'],
        dataLabels: { enabled: false },
        xaxis: {
            categories: @json($chartLabels),
            labels: {
                rotate: -30,
                style: { fontSize: '11px' },
                formatter: function (val) {
                    return val && val.length > 15 ? val.substring(0, 15) + '…' : val;
                }
            }
        },
        yaxis: {
            labels: {
                formatter: function (val) {
                    return '$ ' + val.toLocaleString('es-MX', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    });
                }
            }
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return '$ ' + val.toLocaleString('es-MX', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
        },
        grid: { borderColor: '#f3f4f5' }
    };
    new ApexCharts(document.querySelector('#pendingAuthChart'), opts).render();
})();
</script>
@endif

@endpush
