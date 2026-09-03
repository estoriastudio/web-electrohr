@extends('layouts.app')

@section('page_title', 'Carga de Trabajo — SOLCOM')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Carga de Trabajo</li>
@endsection

@section('content')
@php
    $totalActive   = $activeCounts->sum() + $unassignedCount;
    $totalAssigned = $activeCounts->sum();
    $usersWithWork = $activeCounts->filter(fn($v) => $v > 0)->count();

    // Nombres cortos para el gráfico
    $chartLabels = [];
    $chartData   = [];
    foreach ($ordersUsers as $u) {
        $count = $activeCounts->get($u->id, 0);
        $chartLabels[] = explode(' ', $u->name)[0]; // primer nombre
        $chartData[]   = (int) $count;
    }
    if ($unassignedCount > 0) {
        $chartLabels[] = 'Sin asignar';
        $chartData[]   = (int) $unassignedCount;
    }

    $summaryUsers = $ordersUsers
        ->sortBy(fn($u) => strtolower($u->name))
        ->sortByDesc(fn($u) => (int) $activeCounts->get($u->id, 0))
        ->values();

    $workloadDetails = [];
    foreach ($summaryUsers as $u) {
        $unlinkedDetails = $pendingByUser->get($u->id, collect())->map(function ($pr) {
            return [
                'type' => 'SOLCOM sin OC',
                'folio' => 'SOLCOM #' . str_pad($pr->folio, 5, '0', STR_PAD_LEFT),
                'description' => $pr->short_description ?? '—',
                'project' => $pr->project?->name ?? ($pr->projectWork?->name ?? '—'),
                'zone' => $pr->zone ?? '—',
                'needDate' => $pr->need_date?->format('d/m/Y') ?? '—',
                'url' => route('purchase_requests.show', $pr),
            ];
        });

        $formatOrderDetail = function ($order) {
            $purchaseRequest = $order->purchaseRequest;

            return [
                'type' => $order->status === 'pendiente' ? 'OC pendiente' : 'OC emitida',
                'folio' => 'OC #' . str_pad($order->folio, 5, '0', STR_PAD_LEFT),
                'description' => $purchaseRequest->short_description ?? '—',
                'project' => $purchaseRequest->project?->name ?? ($purchaseRequest->projectWork?->name ?? '—'),
                'zone' => $purchaseRequest->zone ?? '—',
                'needDate' => $purchaseRequest->need_date?->format('d/m/Y') ?? '—',
                'url' => route('purchase_orders.show', $order),
            ];
        };

        $activeOrders = $activeOrdersByUser->get($u->id, collect());
        $pendingDetails = $activeOrders->where('status', 'pendiente')->map($formatOrderDetail);
        $issuedDetails = $activeOrders->where('status', 'emitida')->map($formatOrderDetail);

        $workloadDetails[$u->id] = [
            'name' => $u->name,
            'active' => $unlinkedDetails->concat($pendingDetails)->concat($issuedDetails)->values(),
            'without_order' => $unlinkedDetails->values(),
            'pending' => $pendingDetails->values(),
        ];
    }
@endphp

{{-- ── Gráfico + Tabla resumen ────────────────────────────────────────────── --}}
<div class="row g-4 mb-4">
    {{-- Gráfico de barras --}}
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-bar-chart-grouped-line me-2 text-primary"></i>
                    Carga Activa por Responsable
                </h5>
                <p class="text-muted fs-12 mb-0 mt-1">SOLCOMs sin OC y OC pendientes o emitidas</p>
            </div>
            <div class="card-body">
                @if ($totalActive > 0)
                    <div id="workloadChart" style="min-height:300px;"></div>
                @else
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 py-5 text-muted">
                        <i class="ri-check-double-line fs-1 mb-2 text-success"></i>
                        <p class="mb-0 fw-medium">¡Sin carga activa!</p>
                        <p class="fs-12 mb-0">Todas las órdenes están finalizadas.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-medium fs-13 mb-1">Carga activa</p>
                        <h3 class="mb-0 fw-bold">{{ $totalActive }}</h3>
                        <p class="text-muted fs-12 mb-0">Sin OC, pendientes o emitidas</p>
                    </div>
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-inbox-archive-line fs-24 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-medium fs-13 mb-1">Activas asignadas</p>
                        <h3 class="mb-0 fw-bold">{{ $totalAssigned }}</h3>
                        <p class="text-muted fs-12 mb-0">Con responsable definido</p>
                    </div>
                    <div class="bg-success-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-user-received-line fs-24 text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-medium fs-13 mb-1">Sin asignar</p>
                        <h3 class="mb-0 fw-bold {{ $unassignedCount > 0 ? 'text-danger' : '' }}">{{ $unassignedCount }}</h3>
                        <p class="text-muted fs-12 mb-0">Requieren responsable</p>
                    </div>
                    <div class="bg-danger-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-user-unfollow-line fs-24 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-medium fs-13 mb-1">Compradores Activos</p>
                        <h3 class="mb-0 fw-bold">{{ $usersWithWork }}</h3>
                        <p class="text-muted fs-12 mb-0">Con carga activa</p>
                    </div>
                    <div class="bg-warning-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-team-line fs-24 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de resumen por usuario --}}
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-user-line me-2 text-primary"></i>
                    Resumen por Comprador
                </h5>
                <p class="text-muted fs-12 mb-0 mt-1">Selecciona un indicador para ver su desglose</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Comprador</th>
                                <th class="text-center">Activas</th>
                                <th class="text-center">Sin OC</th>
                                <th class="text-center">Pendientes</th>
                                <th class="text-center">Emitidas</th>
                                <th class="text-center">Finalizadas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summaryUsers as $u)
                            @php
                                $ordersByStatus = $orderCounts->get($u->id, collect())->pluck('total', 'status');
                                $active         = (int) $activeCounts->get($u->id, 0);
                                $withoutOrder   = (int) $unlinkedRequestCounts->get($u->id, 0);
                                $pendingOrders  = (int) $ordersByStatus->get('pendiente', 0);
                                $issuedOrders   = (int) $ordersByStatus->get('emitida', 0);
                                $completedOrders = (int) $ordersByStatus->get('autorizada', 0);
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-xs bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                             style="width:34px;height:34px;">
                                            <span class="fs-12 fw-bold text-primary">
                                                {{ strtoupper(substr($u->name, 0, 1)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="fw-medium fs-14">{{ $u->name }}</div>
                                            <div class="text-muted fs-11">{{ $u->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if ($active > 0)
                                        <button type="button" class="btn btn-xs btn-soft-primary workload-detail-trigger"
                                                data-user-id="{{ $u->id }}" data-indicator="active"
                                                aria-label="Ver carga activa de {{ $u->name }}">
                                            {{ $active }}
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($withoutOrder > 0)
                                        <button type="button" class="btn btn-xs btn-soft-danger workload-detail-trigger"
                                                data-user-id="{{ $u->id }}" data-indicator="without_order"
                                                aria-label="Ver SOLCOMs sin OC de {{ $u->name }}">
                                            {{ $withoutOrder }}
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($pendingOrders > 0)
                                        <button type="button" class="btn btn-xs btn-soft-warning workload-detail-trigger"
                                                data-user-id="{{ $u->id }}" data-indicator="pending"
                                                aria-label="Ver OC pendientes de {{ $u->name }}">
                                            {{ $pendingOrders }}
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="fs-13 {{ $issuedOrders > 0 ? 'text-primary fw-medium' : 'text-muted' }}">
                                        {{ $issuedOrders ?: '—' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fs-13 {{ $completedOrders > 0 ? 'text-success fw-medium' : 'text-muted' }}">
                                        {{ $completedOrders ?: '—' }}
                                    </span>
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

{{-- ── Sin asignar ────────────────────────────────────────────────────────── --}}
@if ($unassignedSolcoms->isNotEmpty())
<div class="row">
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger-subtle border-bottom border-danger-subtle d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0 text-danger">
                        <i class="ri-error-warning-line me-2"></i>
                        SOLCOMs Sin Responsable Asignado
                    </h5>
                    <p class="text-muted fs-12 mb-0 mt-1">
                        Estas SOLCOMs están en estado <em>En Compras</em> pero no tienen un comprador asignado.
                        Asígnalas desde <a href="{{ route('purchase_requests.index') }}?status=sent_to_purchasing">Solicitudes de Compra</a>.
                    </p>
                </div>
                <span class="badge bg-danger fs-13 px-3 py-2">{{ $unassignedCount }} SOLCOM(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Descripción</th>
                                <th>Proyecto</th>
                                <th>Zona</th>
                                <th>Fecha necesidad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unassignedSolcoms as $pr)
                            @php $isUrgent = $pr->need_date && $pr->need_date->lte(\Carbon\Carbon::today()->addDays(5)); @endphp
                            <tr class="{{ $isUrgent ? 'table-warning' : '' }}">
                                <td class="fw-medium">
                                    {{ str_pad($pr->folio, 5, '0', STR_PAD_LEFT) }}
                                    @if ($isUrgent)
                                        <i class="ri-alarm-warning-line text-warning ms-1" title="Urgente"></i>
                                    @endif
                                </td>
                                <td class="text-truncate" style="max-width:260px;" title="{{ $pr->short_description }}">
                                    {{ $pr->short_description ?? '—' }}
                                </td>
                                <td>{{ $pr->project?->name ?? ($pr->projectWork?->name ?? '—') }}</td>
                                <td>{{ $pr->zone ?? '—' }}</td>
                                <td>
                                    @if ($pr->need_date)
                                        <span class="{{ $isUrgent ? 'text-danger fw-medium' : '' }}">
                                            {{ $pr->need_date->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('purchase_requests.show', $pr) }}"
                                       class="btn btn-xs btn-soft-secondary">
                                        <i class="ri-eye-line me-1"></i> Ver
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

<div class="modal fade" id="workloadDetailModal" tabindex="-1" aria-labelledby="workloadDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="workloadDetailModalLabel">Detalle de carga</h5>
                    <p class="text-muted fs-12 mb-0 mt-1" id="workloadDetailModalUser"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Tipo</th>
                                <th>Folio</th>
                                <th>Descripción</th>
                                <th>Proyecto</th>
                                <th>Zona</th>
                                <th>Fecha necesidad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="workloadDetailBody"></tbody>
                    </table>
                </div>
                <div class="d-none py-5 text-center text-muted" id="workloadDetailEmpty">
                    No hay registros para este indicador.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
@if ($totalActive > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const workloadDetails = @json($workloadDetails);

    // ── Gráfico de barras: carga activa por responsable ────────────────────
    const labels = @json($chartLabels);
    const data   = @json($chartData);
    const colors = data.map((v, i) =>
        labels[i] === 'Sin asignar' ? '#ef4444' :
        ['#3b82f6','#10b981','#f59e0b','#8b5cf6','#06b6d4','#f97316'][i % 6]
    );

    const opts = {
        series: [{
            name: 'SOLCOMs',
            data: data,
        }],
        chart: {
            type: 'bar',
            height: 300,
            toolbar: { show: false },
        },
        plotOptions: {
            bar: {
                borderRadius: 6,
                distributed: true,
                columnWidth: '50%',
                dataLabels: { position: 'top' },
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (v) => v > 0 ? v : '',
            offsetY: -22,
            style: { fontSize: '13px', colors: ['#333'] },
        },
        legend: { show: false },
        xaxis: {
            categories: labels,
            labels: { style: { fontSize: '12px' } },
        },
        yaxis: {
            tickAmount: Math.max(...data),
            labels: { formatter: (v) => Math.round(v) },
        },
        colors: colors,
        tooltip: {
            y: { formatter: (v) => v + ' SOLCOM(s)' }
        },
        grid: { borderColor: '#f0f0f0' },
    };

    new ApexCharts(document.querySelector('#workloadChart'), opts).render();

    const detailModal = new bootstrap.Modal(document.getElementById('workloadDetailModal'));
    const modalTitle = document.getElementById('workloadDetailModalLabel');
    const modalUser = document.getElementById('workloadDetailModalUser');
    const modalBody = document.getElementById('workloadDetailBody');
    const modalEmpty = document.getElementById('workloadDetailEmpty');
    const indicatorLabels = {
        active: 'Activas',
        without_order: 'Sin OC',
        pending: 'Pendientes',
    };
    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);

    document.querySelectorAll('.workload-detail-trigger').forEach(button => {
        button.addEventListener('click', function () {
            const userDetail = workloadDetails[this.dataset.userId];
            const indicator = this.dataset.indicator;
            const details = userDetail?.[indicator] ?? [];

            modalTitle.textContent = indicatorLabels[indicator] ?? 'Detalle de carga';
            modalUser.textContent = userDetail?.name ?? '';
            modalBody.innerHTML = details.map((detail) => `
                <tr>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(detail.type)}</span></td>
                    <td class="fw-medium">${escapeHtml(detail.folio)}</td>
                    <td>${escapeHtml(detail.description)}</td>
                    <td>${escapeHtml(detail.project)}</td>
                    <td>${escapeHtml(detail.zone)}</td>
                    <td>${escapeHtml(detail.needDate)}</td>
                    <td class="text-end">
                        <a href="${escapeHtml(detail.url)}" class="btn btn-xs btn-soft-secondary" target="_blank" aria-label="Ver ${escapeHtml(detail.folio)}">
                            <i class="ri-eye-line"></i>
                        </a>
                    </td>
                </tr>
            `).join('');
            modalEmpty.classList.toggle('d-none', details.length > 0);

            detailModal.show();
        });
    });

});
</script>
@endif
@endpush
