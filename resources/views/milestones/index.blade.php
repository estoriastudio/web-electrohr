@extends('layouts.app')

@section('page_title', 'Hitos de Pago')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Hitos de Pago</li>
@endsection

@section('content')

<div class="row mb-3">
    <div class="col-12">
        {{-- Leyenda semáforo --}}
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <span class="fw-semibold text-muted fs-13">Semáforo:</span>
            <span class="d-flex align-items-center gap-1 fs-13">
                <span class="badge rounded-circle p-2" style="background:#28a745;">&nbsp;</span> Al día
            </span>
            <span class="d-flex align-items-center gap-1 fs-13">
                <span class="badge rounded-circle p-2" style="background:#ffc107;">&nbsp;</span> Próximo a vencer (&le;7 días)
            </span>
            <span class="d-flex align-items-center gap-1 fs-13">
                <span class="badge rounded-circle p-2" style="background:#dc3545;">&nbsp;</span> Vencido
            </span>
            <span class="d-flex align-items-center gap-1 fs-13">
                <span class="badge rounded-circle p-2" style="background:#212529;">&nbsp;</span> Completo sin factura
            </span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Hitos de Pago</h4>
                    <p class="text-muted fs-13 mb-0">Todos los hitos ordenados por fecha de vencimiento.</p>
                </div>
                <div>
                    <a href="{{ route('purchase_orders.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-file-list-3-line me-1"></i> Órdenes de Compra
                    </a>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th class="text-center" style="width:50px;">Estado</th>
                                <th>#</th>
                                <th>Orden de Compra</th>
                                <th>Proveedor</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Monto objetivo</th>
                                <th>Cubierto</th>
                                <th>Progreso</th>
                                <th>Vencimiento</th>
                                <th>Facturas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($milestones as $milestone)
                                @php
                                    $order    = $milestone->purchaseOrder;
                                    $supplier = $order->supplier;
                                    $today    = \Carbon\Carbon::today();

                                    $isComplete      = $milestone->is_complete;
                                    $hasInvoice      = $milestone->invoices->isNotEmpty();
                                    $hasDueDate      = !is_null($milestone->due_date);
                                    $isOverdue       = $hasDueDate && $milestone->due_date->lt($today);
                                    $isNearlyDue     = $hasDueDate && !$isOverdue && $milestone->due_date->lte($urgentDate);

                                    // Semáforo (prioridad: negro > rojo > amarillo > verde)
                                    if ($isComplete && !$hasInvoice) {
                                        $semaphore = 'black';
                                        $semTip    = 'Completo sin factura vinculada';
                                        $rowClass  = 'table-dark';
                                    } elseif (!$isComplete && $isOverdue) {
                                        $semaphore = 'red';
                                        $semTip    = 'Vencido';
                                        $rowClass  = 'table-danger';
                                    } elseif (!$isComplete && $isNearlyDue) {
                                        $semaphore = 'yellow';
                                        $semTip    = 'Próximo a vencer';
                                        $rowClass  = 'table-warning';
                                    } else {
                                        $semaphore = 'green';
                                        $semTip    = 'Al día';
                                        $rowClass  = '';
                                    }

                                    $semColors = [
                                        'green'  => '#28a745',
                                        'yellow' => '#ffc107',
                                        'red'    => '#dc3545',
                                        'black'  => '#212529',
                                    ];
                                    $semColor = $semColors[$semaphore];

                                    $effectiveAmount = $milestone->effective_amount;
                                    $progressPct     = $milestone->progress_percent;
                                    $progressClass   = $progressPct >= 100 ? 'bg-success'
                                                     : ($progressPct >= 50 ? 'bg-info' : 'bg-warning');
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    {{-- Semáforo --}}
                                    <td class="text-center">
                                        <span title="{{ $semTip }}"
                                              style="display:inline-block;width:18px;height:18px;border-radius:50%;background:{{ $semColor }};border:2px solid rgba(0,0,0,.15);">
                                        </span>
                                    </td>

                                    <td class="fw-semibold">{{ $milestone->id }}</td>

                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}" class="text-dark fw-medium">
                                            OC #{{ $order->id }}
                                        </a>
                                        <small class="text-muted d-block fs-11">{{ $order->currency }}</small>
                                    </td>

                                    <td>
                                        <a href="{{ route('suppliers.show', $supplier) }}" class="text-dark">
                                            {{ $supplier->rfc_name ?? $supplier->commercial_name ?? '—' }}
                                        </a>
                                    </td>

                                    <td>
                                        <span class="badge {{ $milestone->type === 'anticipo' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }} py-1 px-2 fs-12">
                                            {{ $milestone->type === 'anticipo' ? 'Anticipo' : 'Regular' }}
                                        </span>
                                    </td>

                                    <td>
                                        @if ($milestone->value_type === 'porcentaje')
                                            {{ number_format($milestone->value, 2) }}%
                                        @else
                                            {{ number_format($milestone->value, 2) }}
                                        @endif
                                    </td>

                                    <td class="fw-semibold">
                                        {{ number_format($effectiveAmount, 2) }}
                                    </td>

                                    <td>
                                        {{ number_format($milestone->covered_amount, 2) }}
                                    </td>

                                    <td style="min-width:120px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:6px;">
                                                <div class="progress-bar {{ $progressClass }}"
                                                     role="progressbar"
                                                     style="width:{{ $progressPct }}%"
                                                     aria-valuenow="{{ $progressPct }}"
                                                     aria-valuemin="0"
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                            <span class="fs-11 text-muted">{{ $progressPct }}%</span>
                                        </div>
                                    </td>

                                    <td>
                                        @if ($milestone->due_date)
                                            <span class="{{ $isOverdue && !$isComplete ? 'text-danger fw-semibold' : '' }}">
                                                {{ $milestone->due_date->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        @if ($hasInvoice)
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">
                                                <i class="ri-file-check-line me-1"></i>{{ $milestone->invoices->count() }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">
                                                <i class="ri-file-unknow-line me-1"></i>Sin factura
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}"
                                           class="btn btn-light btn-sm" title="Ver orden de compra">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-5">
                                        <i class="ri-file-list-3-line fs-36 d-block mb-2"></i>
                                        No hay hitos registrados.
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

@endsection
