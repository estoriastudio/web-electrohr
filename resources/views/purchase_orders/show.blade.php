@extends('layouts.app')

@section('page_title', 'Detalle — Orden de Compra #' . $purchaseOrder->id)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_orders.index') }}">Órdenes de Compra</a></li>
    <li class="breadcrumb-item active">OC #{{ $purchaseOrder->id }}</li>
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

@php
    $statusMap = [
        'emitida'    => ['label' => 'Emitida',    'class' => 'bg-info-subtle text-info'],
        'pendiente'  => ['label' => 'Pendiente',  'class' => 'bg-warning-subtle text-warning'],
        'autorizada' => ['label' => 'Autorizada', 'class' => 'bg-success-subtle text-success'],
    ];
    $s = $statusMap[$purchaseOrder->status] ?? ['label' => $purchaseOrder->status, 'class' => 'bg-secondary-subtle text-secondary'];

    $tipoLabel = $purchaseOrder->type === 'materiales_servicios' ? 'Materiales / Servicios' : 'Mantenimiento';
    $totalCubierto = $purchaseOrder->saldo_cubierto;
    $progressTotal = $purchaseOrder->amount > 0
        ? min(100, round(($totalCubierto / $purchaseOrder->amount) * 100, 1))
        : 0;
@endphp

{{-- ── ENCABEZADO ── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1 fw-semibold">
                            OC #{{ $purchaseOrder->id }} —
                            {{ $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? '—' }}
                        </h5>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $tipoLabel }}</span>
                            <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">{{ $purchaseOrder->currency }}</span>
                            @if ($purchaseOrder->recurrence_type === 'recurrente')
                                <span class="badge bg-purple-subtle text-purple py-1 px-2 fs-12">
                                    Recurrente · {{ ucfirst($purchaseOrder->recurrence_frequency) }}
                                </span>
                            @else
                                <span class="badge bg-light text-dark border py-1 px-2 fs-12">Pago único</span>
                            @endif
                        </div>
                        @if ($purchaseOrder->project || $purchaseOrder->site)
                            <p class="card-text text-muted fs-13 mb-1">
                                @if ($purchaseOrder->project)<span><i class="ri-building-2-line me-1"></i>{{ $purchaseOrder->project }}</span>@endif
                                @if ($purchaseOrder->project && $purchaseOrder->site) &nbsp;·&nbsp; @endif
                                @if ($purchaseOrder->site)<span><i class="ri-tools-line me-1"></i>{{ $purchaseOrder->site }}</span>@endif
                            </p>
                        @endif
                    </div>
                    <div class="text-end">
                        <div class="fs-22 fw-semibold text-primary">
                            {{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->amount, 2) }}
                        </div>
                        <div class="text-muted fs-13">
                            Cubierto: <strong>{{ number_format($totalCubierto, 2) }}</strong>
                        </div>
                        <div class="mt-1" style="min-width: 160px;">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: {{ $progressTotal }}%"></div>
                            </div>
                            <small class="text-muted">{{ $progressTotal }}% cubierto</small>
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('purchase_orders.edit', $purchaseOrder) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="ri-edit-line me-1"></i> Editar OC
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateMilestone">
                            <i class="ri-add-line me-1"></i> Agregar hito
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── HITOS ── --}}
<div class="row" id="hitos-container">

    @forelse ($purchaseOrder->milestones as $milestone)
        @php
            $percent = $milestone->progress_percent;
            $isComplete = $milestone->is_complete;
            $progressClass = $isComplete ? 'bg-success' : ($percent >= 50 ? 'bg-warning' : 'bg-danger');
            $tipoHitoMap = ['anticipo' => 'Anticipo', 'regular' => 'Pago Regular'];
            $tipoValorMap = ['fijo' => 'Monto Fijo', 'porcentaje' => 'Porcentaje'];
        @endphp

        <div class="col-md-6 col-xl-4 mb-3">
            <div class="card h-100 {{ $isComplete ? 'border-success' : '' }}">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <div>
                        <span class="fw-semibold fs-14">Hito #{{ $milestone->id }}</span>
                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-11 ms-1">
                            {{ $tipoHitoMap[$milestone->type] ?? $milestone->type }}
                        </span>
                        @if ($isComplete)
                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-11 ms-1">
                                <i class="ri-check-line"></i> Completado
                            </span>
                        @endif
                    </div>
                    <div class="d-flex gap-1">
                        @if ($milestone->payments->count() === 0)
                            <button type="button" class="btn btn-xs btn-soft-primary btn-sm"
                                    title="Editar hito"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditMilestone{{ $milestone->id }}">
                                <i class="ri-edit-line fs-13"></i>
                            </button>
                            <form action="{{ route('milestones.destroy', $milestone) }}" method="POST"
                                  onsubmit="return confirm('¿Eliminar este hito y todos sus pagos?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-soft-danger btn-sm" title="Eliminar hito">
                                    <i class="ri-delete-bin-line fs-13"></i>
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-xs btn-soft-secondary btn-sm"
                                    title="No se puede editar: el hito tiene pagos registrados" disabled>
                                <i class="ri-lock-line fs-13"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="card-body pb-2">
                    {{-- Info principal --}}
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted fs-12">Tipo valor:</span>
                        <span class="fs-12 fw-medium">{{ $tipoValorMap[$milestone->value_type] ?? $milestone->value_type }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted fs-12">Valor:</span>
                        <span class="fs-12 fw-semibold">
                            @if ($milestone->value_type === 'porcentaje')
                                {{ $milestone->value }}%
                            @else
                                {{ $purchaseOrder->currency }} {{ number_format($milestone->value, 2) }}
                            @endif
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted fs-12">Cubierto:</span>
                        <span class="fs-12 fw-semibold text-success">
                            {{ $purchaseOrder->currency }} {{ number_format($milestone->covered_amount, 2) }}
                        </span>
                    </div>
                    @if ($milestone->invoice_date)
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted fs-12">Fecha factura:</span>
                            <span class="fs-12">{{ $milestone->invoice_date->format('d/m/Y') }}</span>
                        </div>
                    @endif
                    @if ($milestone->due_date)
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted fs-12">Vencimiento:</span>
                            <span class="fs-12 {{ $milestone->due_date->isPast() && !$isComplete ? 'text-danger fw-semibold' : '' }}">
                                {{ $milestone->due_date->format('d/m/Y') }}
                            </span>
                        </div>
                    @endif

                    {{-- Barra de progreso --}}
                    <div class="mt-2 mb-1">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Progreso</small>
                            <small class="fw-semibold">{{ $percent }}%</small>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar {{ $progressClass }}" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Pagos del hito --}}
                <div class="card-body pt-0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="fw-semibold text-muted text-uppercase fs-11">Pagos ({{ $milestone->payments->count() }})</small>
                        @if (!$isComplete)
                            <button type="button" class="btn btn-xs btn-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalCreatePayment{{ $milestone->id }}">
                                <i class="ri-add-line"></i> Pago
                            </button>
                        @endif
                    </div>

                    @if ($milestone->payments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Monto</th>
                                        <th>Fecha</th>
                                        <th>Estatus</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($milestone->payments as $payment)
                                        @php
                                            $payStatusMap = [
                                                'por_autorizar' => ['label' => 'Por autorizar', 'class' => 'bg-warning-subtle text-warning'],
                                                'autorizado'    => ['label' => 'Autorizado',    'class' => 'bg-info-subtle text-info'],
                                                'pagado'        => ['label' => 'Pagado',         'class' => 'bg-success-subtle text-success'],
                                            ];
                                            $ps = $payStatusMap[$payment->status] ?? ['label' => $payment->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                        @endphp
                                        <tr>
                                            <td class="fw-medium">{{ $payment->folio }}</td>
                                            <td>{{ number_format($payment->amount, 2) }}</td>
                                            <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                            <td><span class="badge {{ $ps['class'] }} py-1 px-1 fs-10">{{ $ps['label'] }}</span></td>
                                            <td>
                                                <form action="{{ route('payments.destroy', $payment) }}" method="POST"
                                                      onsubmit="return confirm('¿Eliminar este pago?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-soft-danger" style="padding: 1px 6px;" title="Eliminar pago">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted fs-12 mb-0 text-center">Sin pagos registrados.</p>
                    @endif
                </div>
            </div>
        </div>

    @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="ri-flag-line fs-36 d-block mb-2"></i>
                    No hay hitos configurados. Agrega el primero con el botón <strong>"Agregar hito"</strong>.
                </div>
            </div>
        </div>
    @endforelse

</div>

{{-- ── MODALES DE HITOS (fuera del row para posicionamiento correcto) ── --}}
@foreach ($purchaseOrder->milestones as $milestone)
    @php
        $pendiente = max(0, (float)$milestone->value - (float)$milestone->covered_amount);
    @endphp

    {{-- MODAL Editar Hito --}}
    <div class="modal fade" id="modalEditMilestone{{ $milestone->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('milestones.update', $milestone) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-edit-line me-1"></i> Editar Hito #{{ $milestone->id }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" name="type" required>
                                    <option value="anticipo"  {{ $milestone->type === 'anticipo'  ? 'selected' : '' }}>Anticipo (Contado)</option>
                                    <option value="regular"   {{ $milestone->type === 'regular'   ? 'selected' : '' }}>Pago regular</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Tipo de valor <span class="text-danger">*</span></label>
                                <select class="form-select" name="value_type" required>
                                    <option value="fijo"       {{ $milestone->value_type === 'fijo'       ? 'selected' : '' }}>Fijo</option>
                                    <option value="porcentaje" {{ $milestone->value_type === 'porcentaje' ? 'selected' : '' }}>Porcentaje</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Valor <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control"
                                       name="value" value="{{ $milestone->value }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Fecha de factura</label>
                                <input type="date" class="form-control"
                                       name="invoice_date" value="{{ $milestone->invoice_date?->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Fecha vencimiento</label>
                                <input type="date" class="form-control"
                                       name="due_date" value="{{ $milestone->due_date?->format('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL Crear Pago para este hito --}}
    @if (!$milestone->is_complete)
    <div class="modal fade" id="modalCreatePayment{{ $milestone->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('payments.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="milestone_id" value="{{ $milestone->id }}">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-money-dollar-circle-line me-1"></i> Nuevo Pago — Hito #{{ $milestone->id }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 fs-13">
                            Saldo pendiente del hito: <strong>{{ $purchaseOrder->currency }} {{ number_format($pendiente, 2) }}</strong>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Folio</label>
                                <input type="text" class="form-control" name="folio"
                                       placeholder="Dejar vacío para generar automático">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" max="{{ $pendiente }}"
                                       class="form-control" name="amount" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Fecha de pago <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="payment_date"
                                       value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Estatus <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="por_autorizar" selected>Por autorizar</option>
                                    <option value="autorizado">Autorizado</option>
                                    <option value="pagado">Pagado</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium">Número de referencia</label>
                                <input type="text" class="form-control" name="reference_number"
                                       placeholder="Ej. transferencia bancaria, cheque...">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Registrar pago</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

@endforeach

{{-- MODAL Crear Hito --}}
<div class="modal fade" id="modalCreateMilestone" tabindex="-1" aria-labelledby="modalCreateMilestoneLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('milestones.store') }}" method="POST">
                @csrf
                <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateMilestoneLabel">
                        <i class="ri-flag-line me-1"></i> Nuevo Hito
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select" name="type" required>
                                <option value="anticipo">Anticipo (Contado)</option>
                                <option value="regular" selected>Pago regular</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Tipo de valor <span class="text-danger">*</span></label>
                            <select class="form-select" name="value_type" required>
                                <option value="fijo" selected>Fijo</option>
                                <option value="porcentaje">Porcentaje</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Valor <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control"
                                   name="value" placeholder="Ej. 5000.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Fecha de factura</label>
                            <input type="date" class="form-control" name="invoice_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Fecha vencimiento</label>
                            <input type="date" class="form-control" name="due_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Crear hito
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    // Abrir modal de hito si hay errores de validación relacionados
    @if ($errors->hasBag('default') && old('purchase_order_id'))
        var modal = new bootstrap.Modal(document.getElementById('modalCreateMilestone'));
        modal.show();
    @endif
});
</script>
@endpush
