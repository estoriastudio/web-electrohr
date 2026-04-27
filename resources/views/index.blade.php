@extends('layouts.app')

@section('page_title', 'Bienvenida')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">Inicio</a></li>
    <li class="breadcrumb-item active">Bienvenida</li>
@endsection

@section('content')

{{-- ============================================================ --}}
{{-- Bloque 1: Indicadores de Pagos — admin + payments            --}}
{{-- ============================================================ --}}
@if(auth()->user()->hasAnyRole(['admin', 'payments']))
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
@if(auth()->user()->hasAnyRole(['admin', 'orders']))
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
                                            {{ optional($milestone->purchaseOrder->supplier)->name ?? '—' }}
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
@if(auth()->user()->hasAnyRole(['admin', 'payments']))
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

@endsection

@push('scripts')

{{-- Gráfico 3: Urgencias por vencimiento --}}
@if(auth()->user()->hasAnyRole(['admin', 'orders']) && array_sum($urgencyValues) > 0)
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
@if(auth()->user()->hasAnyRole(['admin', 'payments']) && count($chartLabels) > 0)
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
