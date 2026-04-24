@extends('layouts.app')

@section('page_title', 'Autorización de Pagos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Autorización de Pagos</li>
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

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Pagos pendientes de autorización</h4>
                    <p class="text-muted fs-13 mb-0">Los pagos con vencimiento en los próximos 7 días aparecen primero.</p>
                </div>
                <div>
                    <a href="{{ route('purchase_orders.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-file-list-3-line me-1"></i> Ir a Órdenes de Compra
                    </a>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Urgencia</th>
                                <th>Folio</th>
                                <th>Proveedor</th>
                                <th>Orden de Compra</th>
                                <th>Hito</th>
                                <th>Monto</th>
                                <th>Fecha pago</th>
                                <th>Venc. Hito</th>
                                <th>Referencia</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                @php
                                    $milestone  = $payment->milestone;
                                    $order      = $milestone->purchaseOrder;
                                    $supplier   = $order->supplier;
                                    $isUrgent   = $milestone->due_date
                                                    && $milestone->due_date->lte($urgentDate)
                                                    && $payment->status !== 'pagado';

                                    $payStatusMap = [
                                        'por_autorizar' => ['label' => 'Por autorizar', 'class' => 'bg-warning-subtle text-warning'],
                                        'autorizado'    => ['label' => 'Autorizado',    'class' => 'bg-info-subtle text-info'],
                                        'pagado'        => ['label' => 'Pagado',         'class' => 'bg-success-subtle text-success'],
                                    ];
                                    $ps = $payStatusMap[$payment->status] ?? ['label' => $payment->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                @endphp
                                <tr class="{{ $isUrgent ? 'table-warning' : '' }}">
                                    <td>
                                        @if ($isUrgent)
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">
                                                <i class="ri-alarm-warning-line me-1"></i> Urgente
                                            </span>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold">{{ $payment->folio }}</td>
                                    <td>
                                        <a href="{{ route('suppliers.show', $supplier) }}" class="text-dark fw-medium">
                                            {{ $supplier->rfc_name ?? $supplier->commercial_name ?? '—' }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}" class="text-dark">
                                            OC #{{ $order->id }}
                                            <small class="text-muted d-block fs-11">{{ $order->currency }}</small>
                                        </a>
                                    </td>
                                    <td>
                                        Hito #{{ $milestone->id }}
                                        <small class="text-muted d-block fs-11">
                                            {{ $milestone->type === 'anticipo' ? 'Anticipo' : 'Regular' }}
                                        </small>
                                    </td>
                                    <td class="fw-semibold">{{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                    <td>
                                        @if ($milestone->due_date)
                                            <span class="{{ $milestone->due_date->isPast() && $payment->status !== 'pagado' ? 'text-danger fw-semibold' : '' }}">
                                                {{ $milestone->due_date->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $payment->reference_number ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $ps['class'] }} py-1 px-2 fs-12">{{ $ps['label'] }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            {{-- Autorizar --}}
                                            @if ($payment->status === 'por_autorizar')
                                                <form action="{{ route('payments.update', $payment) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="autorizado">
                                                    <button type="submit" class="btn btn-soft-info btn-sm" title="Autorizar"
                                                            onclick="return confirm('¿Autorizar este pago?')">
                                                        <i class="ri-check-line"></i> Autorizar
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Marcar como pagado --}}
                                            @if (in_array($payment->status, ['por_autorizar', 'autorizado']))
                                                <form action="{{ route('payments.update', $payment) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="pagado">
                                                    <button type="submit" class="btn btn-soft-success btn-sm" title="Marcar como pagado"
                                                            onclick="return confirm('¿Marcar como PAGADO? Esto actualizará el saldo cubierto del hito.')">
                                                        <i class="ri-money-dollar-circle-line"></i> Pagado
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Revertir a por autorizar --}}
                                            @if ($payment->status !== 'por_autorizar')
                                                <form action="{{ route('payments.update', $payment) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="por_autorizar">
                                                    <button type="submit" class="btn btn-soft-warning btn-sm" title="Revertir estatus"
                                                            onclick="return confirm('¿Revertir el estatus a «Por autorizar»?')">
                                                        <i class="ri-arrow-go-back-line"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Ver OC --}}
                                            <a href="{{ route('purchase_orders.show', $order) }}"
                                               class="btn btn-light btn-sm" title="Ver orden de compra">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-36 d-block mb-2 text-success"></i>
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

@endsection
