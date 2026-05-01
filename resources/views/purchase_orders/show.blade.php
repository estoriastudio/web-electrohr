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

{{-- ── ALERTA DE AUTORIZACIÓN ── --}}
@if (in_array($purchaseOrder->status, ['emitida', 'pendiente']))
    <div class="alert alert-warning alert-dismissible d-flex align-items-start gap-3 mb-3" role="alert">
        <i class="ri-shield-check-line fs-22 flex-shrink-0 mt-1 text-warning"></i>
        <div class="flex-grow-1">
            <h6 class="alert-heading mb-1 fw-semibold">Orden de compra pendiente de autorización</h6>
            <p class="mb-0 fs-13">
                Esta OC se encuentra en estatus
                <span class="badge {{ $s['class'] }} py-1 px-2 fs-12 ms-1">{{ $s['label'] }}</span>.
                Requiere revisión y aprobación de un administrador antes de proceder con los pagos.
            </p>
        </div>
        @role('admin')
        <form action="{{ route('purchase_orders.approve', $purchaseOrder) }}" method="POST" class="flex-shrink-0 align-self-center">
            @csrf @method('PATCH')
            <button type="submit"
                    class="btn btn-success btn-sm"
                    onclick="return confirm('¿Autorizar la OC #{{ $purchaseOrder->id }}? El estatus cambiará a Autorizada.')">
                <i class="ri-check-double-line me-1"></i> Autorizar OC
            </button>
        </form>
        @endrole
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

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
                        @hasanyrole('admin|orders')
                        <a href="{{ route('purchase_orders.edit', $purchaseOrder) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="ri-edit-line me-1"></i> Editar OC
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateMilestone">
                            <i class="ri-add-line me-1"></i> Agregar hito
                        </button>
                        @endhasanyrole
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
            $percent     = $milestone->progress_percent;
            $isComplete  = $milestone->is_complete;
            $hasInvoice  = $milestone->invoices->isNotEmpty();
            $progressClass = $isComplete ? 'bg-success' : ($percent >= 50 ? 'bg-warning' : 'bg-danger');
            $tipoHitoMap = ['anticipo' => 'Anticipo', 'regular' => 'Pago Regular'];
            $tipoValorMap = ['fijo' => 'Monto Fijo', 'porcentaje' => 'Porcentaje'];

            $today      = \Carbon\Carbon::today();
            $urgentDate = $today->copy()->addDays(7);
            $hasDueDate = !is_null($milestone->due_date);
            $isOverdue  = $hasDueDate && $milestone->due_date->lt($today);
            $isNearlyDue = $hasDueDate && !$isOverdue && $milestone->due_date->lte($urgentDate);

            // Semáforo: misma lógica que milestones/index (negro > rojo > amarillo > verde)
            if ($isComplete && !$hasInvoice) {
                $semBorder   = 'border-dark';
                $semHeader   = 'bg-dark text-white';
                $semDotColor = '#212529';
                $semLabel    = 'Sin factura';
                $semIcon     = 'ri-file-unknow-line';
                $semBadge    = 'bg-dark text-white';
            } elseif (!$isComplete && $isOverdue) {
                $semBorder   = 'border-danger';
                $semHeader   = 'bg-danger-subtle';
                $semDotColor = '#dc3545';
                $semLabel    = 'Vencido';
                $semIcon     = 'ri-alarm-warning-line';
                $semBadge    = 'bg-danger-subtle text-danger';
            } elseif (!$isComplete && $isNearlyDue) {
                $semBorder   = 'border-warning';
                $semHeader   = 'bg-warning-subtle';
                $semDotColor = '#ffc107';
                $semLabel    = 'Próximo a vencer';
                $semIcon     = 'ri-time-line';
                $semBadge    = 'bg-warning-subtle text-warning';
            } elseif ($isComplete) {
                $semBorder   = 'border-success';
                $semHeader   = 'bg-success-subtle';
                $semDotColor = '#28a745';
                $semLabel    = 'Completado';
                $semIcon     = 'ri-check-double-line';
                $semBadge    = 'bg-success-subtle text-success';
            } else {
                $semBorder   = 'border-success';
                $semHeader   = '';
                $semDotColor = '#28a745';
                $semLabel    = 'Al día';
                $semIcon     = 'ri-checkbox-circle-line';
                $semBadge    = 'bg-success-subtle text-success';
            }

            // Tiempo relativo al vencimiento (legible para humanos)
            $dueLabel = null;
            $dueLabelClass = 'text-muted';
            if ($hasDueDate) {
                $diffDays = $today->diffInDays($milestone->due_date, false); // negativo = pasado
                if ($diffDays === 0) {
                    $dueLabel = 'Vence hoy';
                    $dueLabelClass = 'text-danger fw-semibold';
                } elseif ($diffDays < 0) {
                    $abs = abs($diffDays);
                    $dueLabel = 'Hace ' . $abs . ' ' . ($abs === 1 ? 'día' : 'días');
                    $dueLabelClass = $isComplete ? 'text-muted' : 'text-danger fw-semibold';
                } elseif ($diffDays <= 7) {
                    $dueLabel = 'En ' . $diffDays . ' ' . ($diffDays === 1 ? 'día' : 'días');
                    $dueLabelClass = 'text-warning fw-semibold';
                } elseif ($diffDays <= 30) {
                    $weeks = (int) ceil($diffDays / 7);
                    $dueLabel = 'En ~' . $weeks . ' ' . ($weeks === 1 ? 'semana' : 'semanas');
                    $dueLabelClass = 'text-body';
                } else {
                    $months = (int) ceil($diffDays / 30);
                    $dueLabel = 'En ~' . $months . ' ' . ($months === 1 ? 'mes' : 'meses');
                    $dueLabelClass = 'text-muted';
                }
            }
        @endphp

        <div class="col-md-6 col-xl-6 mb-3">
            <div class="card h-100 {{ $semBorder }} border-2">
                <div class="card-header d-flex justify-content-between align-items-center py-2 {{ $semHeader }}">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        {{-- Indicador semáforo --}}
                        <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $semDotColor }};flex-shrink:0;"></span>
                        <span class="fw-semibold fs-14">Hito #{{ $milestone->id }}</span>
                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-11">
                            {{ $tipoHitoMap[$milestone->type] ?? $milestone->type }}
                        </span>
                        <span class="badge {{ $semBadge }} py-1 px-2 fs-11">
                            <i class="{{ $semIcon }} me-1"></i>{{ $semLabel }}
                        </span>
                    </div>
                    <div class="d-flex gap-1">
                        @hasanyrole('admin|orders')
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
                        @endhasanyrole
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
                            <span class="fs-12 text-end">
                                <span class="{{ $isOverdue && !$isComplete ? 'text-danger fw-semibold' : '' }}">
                                    {{ $milestone->due_date->format('d/m/Y') }}
                                </span>
                                @if ($dueLabel)
                                    <span class="d-block fs-11 {{ $dueLabelClass }}">{{ $dueLabel }}</span>
                                @endif
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
                        @hasanyrole('admin|payments')
                        @if (!$isComplete)
                            @if ($purchaseOrder->status === 'autorizada')
                                <button type="button" class="btn btn-xs btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalCreatePayment{{ $milestone->id }}">
                                    <i class="ri-add-line"></i> Pago
                                </button>
                            @else
                                <button type="button" class="btn btn-xs btn-primary btn-sm"
                                        disabled
                                        title="La OC debe estar Autorizada para registrar pagos">
                                    <i class="ri-add-line"></i> Pago
                                </button>
                            @endif
                        @endif
                        @endhasanyrole
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
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($milestone->payments as $payment)
                                        @php
                                            $payStatusMap = [
                                                'por_autorizar' => ['label' => 'Por autorizar', 'class' => 'bg-warning-subtle text-warning'],
                                                'autorizado'    => ['label' => 'Autorizado',    'class' => 'bg-info-subtle text-info'],
                                                'pagado'        => ['label' => 'Pagado',        'class' => 'bg-success-subtle text-success'],
                                                'rechazado'     => ['label' => 'Rechazado',     'class' => 'bg-danger-subtle text-danger'],
                                            ];
                                            $ps = $payStatusMap[$payment->status] ?? ['label' => $payment->status, 'class' => 'bg-secondary-subtle text-secondary'];

                                            // Transiciones permitidas por estatus
                                            $transitions = match ($payment->status) {
                                                'por_autorizar' => [
                                                    'autorizado' => ['label' => 'Autorizar',  'icon' => 'ri-check-line',           'btn' => 'btn-soft-info',    'confirm' => '¿Autorizar este pago?'],
                                                    'rechazado'  => ['label' => 'Rechazar',   'icon' => 'ri-close-circle-line',    'btn' => 'btn-soft-danger',  'confirm' => '¿Rechazar este pago?'],
                                                ],
                                                'autorizado' => [
                                                    'pagado' => ['label' => 'Marcar pagado', 'icon' => 'ri-money-dollar-circle-line', 'btn' => 'btn-soft-success', 'confirm' => '¿Marcar como PAGADO? Esto actualizará el saldo del hito.'],
                                                ],
                                                'rechazado' => [
                                                    'por_autorizar' => ['label' => 'Reactivar', 'icon' => 'ri-arrow-go-back-line', 'btn' => 'btn-soft-warning', 'confirm' => '¿Reactivar este pago a «Por autorizar»?'],
                                                ],
                                                default => [], // pagado: sin transiciones
                                            };
                                        @endphp
                                        <tr>
                                            <td class="fw-medium">{{ $payment->folio }}</td>
                                            <td>{{ number_format($payment->amount, 2) }}</td>
                                            <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge {{ $ps['class'] }} py-1 px-1 fs-10">{{ $ps['label'] }}</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    {{-- Botones de transición de estatus --}}
                                                    @foreach ($transitions as $newStatus => $transition)
                                                        @if ($newStatus === 'autorizado')
                                                            @role('admin')
                                                            <form action="{{ route('payments.update', $payment) }}" method="POST">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status" value="{{ $newStatus }}">
                                                                <button type="submit"
                                                                        class="btn btn-xs {{ $transition['btn'] }}"
                                                                        title="{{ $transition['label'] }}"
                                                                        onclick="return confirm('{{ $transition['confirm'] }}')">
                                                                    <i class="{{ $transition['icon'] }}"></i>
                                                                    {{ $transition['label'] }}
                                                                </button>
                                                            </form>
                                                            @endrole
                                                        @else
                                                            @hasanyrole('admin|payments')
                                                            <form action="{{ route('payments.update', $payment) }}" method="POST">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status" value="{{ $newStatus }}">
                                                                <button type="submit"
                                                                        class="btn btn-xs {{ $transition['btn'] }}"
                                                                        title="{{ $transition['label'] }}"
                                                                        onclick="return confirm('{{ $transition['confirm'] }}')">
                                                                    <i class="{{ $transition['icon'] }}"></i>
                                                                    {{ $transition['label'] }}
                                                                </button>
                                                            </form>
                                                            @endhasanyrole
                                                        @endif
                                                    @endforeach

                                                    {{-- Eliminar (solo admin|payments) --}}
                                                    @hasanyrole('admin|payments')
                                                    <form action="{{ route('payments.destroy', $payment) }}" method="POST"
                                                          onsubmit="return confirm('¿Eliminar este pago?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-xs btn-soft-danger" title="Eliminar pago">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </form>
                                                    @endhasanyrole
                                                </div>
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

{{-- ── FACTURAS ── --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center py-2">
                <h5 class="card-title mb-0">
                    <i class="ri-file-pdf-line me-1 text-danger"></i> Facturas
                    <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $purchaseOrder->invoices->count() }}</span>
                </h5>
                @hasanyrole('admin|payments')
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modalCreateInvoice">
                    <i class="ri-upload-2-line me-1"></i> Subir factura
                </button>
                @endhasanyrole
            </div>

            @if ($purchaseOrder->invoices->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Archivo</th>
                                <th>Fecha</th>
                                <th>Moneda</th>
                                <th class="text-end">Importe</th>
                                <th>Hitos vinculados</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchaseOrder->invoices as $invoice)
                                <tr>
                                    <td>
                                        <i class="ri-file-pdf-2-line text-danger me-1"></i>
                                        <span class="fw-medium">{{ $invoice->file_name }}</span>
                                    </td>
                                    <td class="text-nowrap fs-12">
                                        {{ $invoice->attached_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                            {{ $invoice->currency }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        {{ number_format($invoice->amount, 2) }}
                                    </td>
                                    <td>
                                        @forelse ($invoice->milestones as $im)
                                            <span class="badge bg-info-subtle text-info py-1 px-2 fs-11 me-1">
                                                Hito #{{ $im->id }}
                                            </span>
                                        @empty
                                            <span class="text-muted fs-12">—</span>
                                        @endforelse
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            @hasanyrole('admin|payments')
                                            <a href="{{ route('invoices.download', $invoice) }}"
                                               target="_blank"
                                               class="btn btn-xs btn-soft-primary" style="padding: 2px 8px;"
                                               title="Ver / Descargar PDF">
                                                <i class="ri-download-2-line"></i>
                                            </a>
                                            <form action="{{ route('invoices.destroy', $invoice) }}" method="POST"
                                                  onsubmit="return confirm('¿Eliminar la factura {{ $invoice->file_name }}? Esta acción no se puede deshacer.')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-soft-danger"
                                                        style="padding: 2px 8px;" title="Eliminar factura">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                            @endhasanyrole
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end fw-semibold fs-13">Total facturado:</td>
                                <td class="text-end fw-bold text-primary">
                                    {{ number_format($purchaseOrder->invoices->sum('amount'), 2) }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="card-body text-center text-muted py-4">
                    <i class="ri-file-pdf-line fs-36 d-block mb-2 text-danger opacity-50"></i>
                    No hay facturas registradas. Usa el botón <strong>"Subir factura"</strong> para agregar la primera.
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ── MODALES DE HITOS (fuera del row para posicionamiento correcto) ── --}}
@foreach ($purchaseOrder->milestones as $milestone)
    @php
        $pendiente = max(0, $milestone->effective_amount - (float)$milestone->covered_amount);
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

{{-- MODAL Subir Factura --}}
<div class="modal fade" id="modalCreateInvoice" tabindex="-1" aria-labelledby="modalCreateInvoiceLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('invoices.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateInvoiceLabel">
                        <i class="ri-file-pdf-line me-1 text-danger"></i> Subir Factura — OC #{{ $purchaseOrder->id }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Archivo PDF --}}
                        <div class="col-12">
                            <label class="form-label fw-medium">
                                Archivo PDF <span class="text-danger">*</span>
                                <small class="text-muted fw-normal">(máx. 10 MB)</small>
                            </label>
                            <input type="file" class="form-control @error('pdf_file') is-invalid @enderror"
                                   name="pdf_file" accept=".pdf" required>
                            <div class="form-text">
                                El nombre se generará automáticamente:
                                <strong>OC{{ $purchaseOrder->id }}-FACT{{ $purchaseOrder->invoices->count() + 1 }}.pdf</strong>
                            </div>
                            @error('pdf_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Importe y Moneda --}}
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Importe <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="{{ $purchaseOrder->amount }}"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   name="amount" placeholder="0.00" required>
                            <div class="form-text">Máximo: {{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->amount, 2) }}</div>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-medium">Moneda</label>
                            <input type="text" class="form-control bg-light" value="{{ $purchaseOrder->currency }}" disabled>
                            <input type="hidden" name="currency" value="{{ $purchaseOrder->currency }}">
                            <div class="form-text">Dato heredado de la Orden de Compra.</div>
                        </div>

                        {{-- Hitos relacionados --}}
                        @if ($purchaseOrder->milestones->count() > 0)
                            <div class="col-12">
                                <label class="form-label fw-medium">Hitos relacionados</label>
                                <div class="border rounded p-3 bg-light">
                                    <div class="row g-2">
                                        @foreach ($purchaseOrder->milestones as $m)
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="milestone_ids[]" value="{{ $m->id }}"
                                                           id="inv_milestone_{{ $m->id }}">
                                                    <label class="form-check-label fs-13" for="inv_milestone_{{ $m->id }}">
                                                        <strong>Hito #{{ $m->id }}</strong>
                                                        <span class="text-muted">
                                                            — {{ $m->type === 'anticipo' ? 'Anticipo' : 'Regular' }}
                                                            · {{ $purchaseOrder->currency }} {{ number_format($m->effective_amount, 2) }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="form-text">Selecciona los hitos que cubre esta factura (opcional).</div>
                                @error('milestone_ids')<div class="text-danger fs-12 mt-1">{{ $message }}</div>@enderror
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-upload-2-line me-1"></i> Subir factura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                            <select class="form-select" name="value_type" id="milestoneValueType" required>
                                <option value="fijo" selected>Fijo</option>
                                <option value="porcentaje">Porcentaje</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Valor <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01"
                                   max="{{ $purchaseOrder->amount }}"
                                   class="form-control" id="milestoneValue"
                                   name="value" placeholder="Ej. 5000.00" required>
                            <div class="invalid-feedback" id="milestoneValueFeedback"></div>
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

    // ── Validación dinámica del campo Valor en Nuevo Hito ──
    var ocAmount = {{ $purchaseOrder->amount }};

    function updateMilestoneValueConstraints() {
        var type  = $('#milestoneValueType').val();
        var $input = $('#milestoneValue');

        if (type === 'porcentaje') {
            $input.attr('max', 100).attr('placeholder', 'Ej. 30 (máx. 100%)');
        } else {
            $input.attr('max', ocAmount).attr('placeholder', 'Ej. 5000.00');
        }

        // Re-validar si ya hay un valor capturado
        if ($input.val() !== '') {
            validateMilestoneValue();
        }
    }

    function validateMilestoneValue() {
        var type   = $('#milestoneValueType').val();
        var val    = parseFloat($('#milestoneValue').val());
        var $input = $('#milestoneValue');
        var $fb    = $('#milestoneValueFeedback');
        var valid  = true;
        var msg    = '';

        if (type === 'porcentaje' && val > 100) {
            valid = false;
            msg   = 'El porcentaje no puede ser mayor a 100%.';
        } else if (type === 'fijo' && val > ocAmount) {
            valid = false;
            msg   = 'El valor fijo no puede superar el importe total de la OC (' + ocAmount.toLocaleString('es-MX', {minimumFractionDigits:2}) + ').';
        }

        if (!valid) {
            $input.addClass('is-invalid').removeClass('is-valid');
            $fb.text(msg);
        } else {
            $input.removeClass('is-invalid').addClass('is-valid');
            $fb.text('');
        }

        return valid;
    }

    $('#milestoneValueType').on('change', updateMilestoneValueConstraints);
    $('#milestoneValue').on('input blur', validateMilestoneValue);

    // Bloquear submit si hay error
    $('#modalCreateMilestone form').on('submit', function (e) {
        if ($('#milestoneValue').val() !== '' && !validateMilestoneValue()) {
            e.preventDefault();
        }
    });

    // Limpiar estado al cerrar el modal
    $('#modalCreateMilestone').on('hidden.bs.modal', function () {
        $('#milestoneValue').val('').removeClass('is-invalid is-valid');
        $('#milestoneValueFeedback').text('');
        $('#milestoneValueType').val('fijo').trigger('change');
    });

    // Inicializar
    updateMilestoneValueConstraints();
});
</script>
@endpush
