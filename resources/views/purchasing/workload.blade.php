@extends('layouts.app')

@section('page_title', 'Carga de Trabajo — SOLCOM')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Carga de Trabajo</li>
@endsection

@section('content')

@php
    $totalPending    = $pendingCounts->sum();
    $totalAssigned   = $pendingCounts->filter(fn($v, $k) => !is_null($k))->sum();
    $usersWithWork   = $pendingCounts->filter(fn($v) => $v > 0)->count();

    $statusMap = [
        'pending'            => ['label' => 'Pendiente',           'class' => 'bg-warning-subtle text-warning'],
        'linked'             => ['label' => 'Ligado',              'class' => 'bg-secondary-subtle text-secondary'],
        'sent_to_purchasing' => ['label' => 'En Compras',          'class' => 'bg-primary-subtle text-primary'],
        'changes_requested'  => ['label' => 'Cambios Solicitados', 'class' => 'bg-danger-subtle text-danger'],
        'completed'          => ['label' => 'Finalizado',          'class' => 'bg-success-subtle text-success'],
    ];

    // Nombres cortos para el gráfico
    $chartLabels = [];
    $chartData   = [];
    foreach ($ordersUsers as $u) {
        $count = $pendingCounts->get($u->id, 0);
        $chartLabels[] = explode(' ', $u->name)[0]; // primer nombre
        $chartData[]   = (int) $count;
    }
    if ($unassignedCount > 0) {
        $chartLabels[] = 'Sin asignar';
        $chartData[]   = (int) $unassignedCount;
    }
@endphp

{{-- ── Tarjetas resumen ───────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-medium fs-13 mb-1">Total en Compras</p>
                        <h3 class="mb-0 fw-bold">{{ $totalPending }}</h3>
                        <p class="text-muted fs-12 mb-0">SOLCOMs pendientes de OC</p>
                    </div>
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-inbox-archive-line fs-24 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-medium fs-13 mb-1">Asignadas</p>
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
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
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
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-medium fs-13 mb-1">Compradores Activos</p>
                        <h3 class="mb-0 fw-bold">{{ $usersWithWork }}</h3>
                        <p class="text-muted fs-12 mb-0">Con SOLCOMs asignadas</p>
                    </div>
                    <div class="bg-warning-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-team-line fs-24 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Gráfico + Tabla resumen ────────────────────────────────────────────── --}}
<div class="row g-4 mb-4">

    {{-- Gráfico de barras --}}
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-bar-chart-grouped-line me-2 text-primary"></i>
                    SOLCOMs Pendientes por Responsable
                </h5>
                <p class="text-muted fs-12 mb-0 mt-1">Estado: <em>En Compras</em></p>
            </div>
            <div class="card-body">
                @if ($totalPending > 0)
                    <div id="workloadChart" style="min-height:300px;"></div>
                @else
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 py-5 text-muted">
                        <i class="ri-check-double-line fs-1 mb-2 text-success"></i>
                        <p class="mb-0 fw-medium">¡Sin pendientes!</p>
                        <p class="fs-12 mb-0">Todas las SOLCOMs están al día.</p>
                    </div>
                @endif
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
                <p class="text-muted fs-12 mb-0 mt-1">Click en una fila para ver el detalle</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Comprador</th>
                                <th class="text-center">Pendientes</th>
                                <th class="text-center">En Compras</th>
                                <th class="text-center">Cambios Req.</th>
                                <th class="text-center">Finalizadas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ordersUsers as $u)
                            @php
                                $counts    = $allCounts->get($u->id, collect());
                                $byStatus  = $counts->pluck('total', 'status');
                                $pending   = (int) $pendingCounts->get($u->id, 0);
                                $changesReq = (int) ($byStatus->get('changes_requested', 0));
                                $completed  = (int) ($byStatus->get('completed', 0));
                                $inBuying   = (int) ($byStatus->get('sent_to_purchasing', 0));
                                $hasSolcoms = $pendingByUser->has($u->id);
                            @endphp
                            <tr class="{{ $hasSolcoms ? 'cursor-pointer user-row' : '' }}"
                                data-user-id="{{ $u->id }}" style="cursor: {{ $hasSolcoms ? 'pointer' : 'default' }}">
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
                                    @if ($pending > 0)
                                        <span class="badge bg-primary rounded-pill fs-12 px-2 py-1">{{ $pending }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="fs-13 {{ $inBuying > 0 ? 'fw-medium text-primary' : 'text-muted' }}">
                                        {{ $inBuying ?: '—' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if ($changesReq > 0)
                                        <span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1">{{ $changesReq }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="fs-13 {{ $completed > 0 ? 'text-success fw-medium' : 'text-muted' }}">
                                        {{ $completed ?: '—' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if ($hasSolcoms)
                                    <button class="btn btn-xs btn-soft-primary toggle-detail" data-user-id="{{ $u->id }}">
                                        <i class="ri-arrow-down-s-line" id="chevron-{{ $u->id }}"></i>
                                    </button>
                                    @endif
                                </td>
                            </tr>

                            {{-- Fila de detalle colapsable --}}
                            @if ($hasSolcoms)
                            <tr class="detail-row d-none" id="detail-{{ $u->id }}">
                                <td colspan="6" class="p-0">
                                    <div class="bg-light border-top border-bottom px-4 py-3">
                                        <p class="text-muted fs-12 fw-medium mb-2">
                                            <i class="ri-stack-line me-1"></i>
                                            SOLCOMs pendientes de generar OC — {{ $u->name }}
                                        </p>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0 bg-white">
                                                <thead class="table-light">
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
                                                    @foreach ($pendingByUser->get($u->id) as $pr)
                                                    @php
                                                        $isUrgent = $pr->need_date && $pr->need_date->lte(\Carbon\Carbon::today()->addDays(5));
                                                    @endphp
                                                    <tr class="{{ $isUrgent ? 'table-warning' : '' }}">
                                                        <td class="fw-medium">
                                                            {{ str_pad($pr->folio, 5, '0', STR_PAD_LEFT) }}
                                                            @if ($isUrgent)
                                                                <i class="ri-alarm-warning-line text-warning ms-1" title="Urgente"></i>
                                                            @endif
                                                        </td>
                                                        <td class="text-truncate" style="max-width:220px;" title="{{ $pr->short_description }}">
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
                                                               class="btn btn-xs btn-soft-secondary" target="_blank">
                                                                <i class="ri-eye-line"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endif

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

@endsection

@push('scripts')
@if ($totalPending > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Gráfico de barras: SOLCOMs pendientes por responsable ──────────────
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

    // ── Toggle filas de detalle ─────────────────────────────────────────────
    document.querySelectorAll('.toggle-detail').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const userId   = this.dataset.userId;
            const detailRow = document.getElementById('detail-' + userId);
            const chevron   = document.getElementById('chevron-' + userId);

            detailRow.classList.toggle('d-none');
            chevron.classList.toggle('ri-arrow-down-s-line');
            chevron.classList.toggle('ri-arrow-up-s-line');
        });
    });

    // También al hacer click en la fila entera
    document.querySelectorAll('.user-row').forEach(row => {
        row.addEventListener('click', function () {
            const userId = this.dataset.userId;
            const btn    = document.querySelector('.toggle-detail[data-user-id="' + userId + '"]');
            if (btn) btn.click();
        });
    });

});
</script>
@endif
@endpush
