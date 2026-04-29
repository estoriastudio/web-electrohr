@extends('layouts.app')

@push('styles')
<style>
    .profile-completeness-bar { height: 8px; }
    .missing-fields-list { columns: 2; gap: 1rem; }
    .kpi-card .card-body { padding: 1.25rem; }
</style>
@endpush

@section('page_title', $supplier->rfc_name ?? $supplier->commercial_name ?? 'Detalle del proveedor')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Proveedores</a></li>
    <li class="breadcrumb-item active">{{ $supplier->rfc_name ?? $supplier->commercial_name ?? 'Detalle' }}</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ── Encabezado de la vista ───────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h5 class="fw-semibold mb-1">{{ $supplier->rfc_name ?? $supplier->commercial_name ?? '—' }}</h5>
        @if ($supplier->commercial_name && $supplier->rfc_name)
            <p class="text-muted mb-0 fs-13">{{ $supplier->commercial_name }}</p>
        @endif
    </div>
    @role('admin')
    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary btn-sm">
        <i class="ri-edit-line me-1"></i> Editar perfil
    </a>
    @endrole
</div>


{{-- ══════════════════════════════════════════════════════════════
     FILA 1 — Completitud del perfil + KPI cards
══════════════════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-3">

    {{-- Tarjeta de completitud --}}
    <div class="col-xl-4 col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="card-title mb-0 fw-semibold">Información del perfil</h6>
                    <span class="badge
                        @if($supplier->profile_completeness >= 80) bg-success-subtle text-success
                        @elseif($supplier->profile_completeness >= 40) bg-warning-subtle text-warning
                        @else bg-danger-subtle text-danger
                        @endif py-1 px-2 fs-12 fw-semibold">
                        {{ $supplier->profile_completeness }}%
                    </span>
                </div>

                <div class="progress profile-completeness-bar mb-3">
                    <div class="progress-bar
                        @if($supplier->profile_completeness >= 80) bg-success
                        @elseif($supplier->profile_completeness >= 40) bg-warning
                        @else bg-danger
                        @endif"
                        role="progressbar"
                        style="width: {{ $supplier->profile_completeness }}%"
                        aria-valuenow="{{ $supplier->profile_completeness }}"
                        aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>

                @if (count($supplier->missing_fields) > 0)
                    <p class="text-muted fs-12 mb-2">Campos pendientes:</p>
                    <ul class="list-unstyled mb-0 missing-fields-list">
                        @foreach ($supplier->missing_fields as $field)
                            <li class="fs-12 text-muted">
                                <i class="ri-checkbox-blank-circle-line me-1 text-warning"></i>{{ $field }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-success fs-13 mb-0">
                        <i class="ri-checkbox-circle-line me-1"></i> Perfil completo
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="col-xl-8 col-lg-7">
        <div class="row g-3 h-100">

            {{-- Órdenes de compra --}}
            <div class="col-sm-6 col-xl-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-file-list-3-line text-primary fs-18"></i>
                            </div>
                            <span class="text-muted fs-12 fw-medium">Órdenes de compra</span>
                        </div>
                        <h4 class="fw-bold mb-0">{{ $supplier->purchase_orders_count ?? 0 }}</h4>
                    </div>
                </div>
            </div>

            {{-- Hitos (milestones) --}}
            <div class="col-sm-6 col-xl-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div class="avatar-sm bg-info bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-flag-line text-info fs-18"></i>
                            </div>
                            <span class="text-muted fs-12 fw-medium">Hitos totales</span>
                        </div>
                        <h4 class="fw-bold mb-0">{{ $milestonesCount }}</h4>
                    </div>
                </div>
            </div>

            {{-- Saldo pagado --}}
            <div class="col-sm-6 col-xl-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div class="avatar-sm bg-success bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-money-dollar-circle-line text-success fs-18"></i>
                            </div>
                            <span class="text-muted fs-12 fw-medium">Saldo pagado</span>
                        </div>
                        <h4 class="fw-bold mb-0">${{ number_format($saldoPagado, 2) }}</h4>
                    </div>
                </div>
            </div>

            {{-- Saldo pendiente --}}
            <div class="col-sm-6 col-xl-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-time-line text-warning fs-18"></i>
                            </div>
                            <span class="text-muted fs-12 fw-medium">Saldo pendiente</span>
                        </div>
                        <h4 class="fw-bold mb-0">${{ number_format($saldoPendiente, 2) }}</h4>
                    </div>
                </div>
            </div>

            {{-- Siguiente hito pendiente --}}
            <div class="col-sm-6 col-xl-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div class="avatar-sm bg-secondary bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-calendar-event-line text-secondary fs-18"></i>
                            </div>
                            <span class="text-muted fs-12 fw-medium">Próximo hito</span>
                        </div>
                        <p class="fw-semibold mb-0 fs-14">{{ $proximoHito?->due_date->format('d/m/Y') ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Fecha de registro --}}
            <div class="col-sm-6 col-xl-4">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-calendar-check-line text-primary fs-18"></i>
                            </div>
                            <span class="text-muted fs-12 fw-medium">Fecha de registro</span>
                        </div>
                        <p class="fw-semibold mb-0 fs-14">{{ $supplier->created_at->format('d/m/Y') }}</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     FILA 2 — Info general del proveedor
══════════════════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-building-line me-1 text-muted"></i> Información general
                </h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted fw-normal fs-13">Razón social</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->rfc_name ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Nombre comercial</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->commercial_name ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">RFC</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->rfc_num ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Correo electrónico</dt>
                    <dd class="col-sm-7 fw-medium fs-14">
                        @if($supplier->email)
                            <a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>
                        @else —
                        @endif
                    </dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Teléfono</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->phone ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Celular</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->cellphone ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Dirección</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->address ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Atendido por</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->attended_by ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Estatus</dt>
                    <dd class="col-sm-7">
                        @php
                            $statusMap = [
                                'active'      => ['label' => 'Activo',    'class' => 'bg-success-subtle text-success'],
                                'inactive'    => ['label' => 'Inactivo',  'class' => 'bg-warning-subtle text-warning'],
                                'blacklisted' => ['label' => 'Bloqueado', 'class' => 'bg-danger-subtle text-danger'],
                            ];
                            $s = $statusMap[$supplier->status] ?? ['label' => 'Sin definir', 'class' => 'bg-secondary-subtle text-secondary'];
                        @endphp
                        <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-bank-line me-1 text-muted"></i> Datos bancarios
                </h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted fw-normal fs-13">Banco</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->bank_name ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Cuenta</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->bank_account ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">CLABE</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->bank_clabe ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">SWIFT</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->swift_code ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Moneda</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->currency ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     FILA 3 — Órdenes de compra
══════════════════════════════════════════════════════════════════ --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-file-list-3-line me-1 text-muted"></i> Órdenes de compra
                </h5>
                {{-- Botón futuro para crear orden --}}
                {{-- <button class="btn btn-sm btn-primary">Nueva orden</button> --}}
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th># Orden</th>
                                <th>Descripción</th>
                                <th>Hitos</th>
                                <th>Importe total</th>
                                <th>Estatus</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $statusMap = [
                                    'emitida'    => ['label' => 'Emitida',    'class' => 'bg-info-subtle text-info'],
                                    'pendiente'  => ['label' => 'Pendiente',  'class' => 'bg-warning-subtle text-warning'],
                                    'autorizada' => ['label' => 'Autorizada', 'class' => 'bg-success-subtle text-success'],
                                ];
                            @endphp
                            @forelse ($supplier->purchaseOrders as $order)
                                @php
                                    $os = $statusMap[$order->status] ?? ['label' => $order->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                    $tipoLabel = $order->type === 'materiales_servicios' ? 'Materiales / Servicios' : 'Mantenimiento';
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}" class="fw-semibold text-dark">
                                            OC #{{ $order->id }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fs-13">{{ $tipoLabel }}</span>
                                        @if ($order->project || $order->site)
                                            <small class="text-muted d-block fs-11">
                                                {{ implode(' · ', array_filter([$order->project, $order->site])) }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>{{ $order->milestones_count }}</td>
                                    <td class="fw-semibold">
                                        {{ $order->currency }} {{ number_format($order->amount, 2) }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $os['class'] }} py-1 px-2 fs-12">{{ $os['label'] }}</span>
                                    </td>
                                    <td class="text-muted fs-12">{{ $order->created_at->format('d/m/Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-file-list-3-line fs-24 d-block mb-1 opacity-50"></i>
                                        Sin órdenes de compra registradas aún.
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

<div class="row">
    <div class="col-12">
        @include('layouts.utilities._log_table', [
            'model_type' => 'Supplier',
            'model_id'   => $supplier->id,
        ])
    </div>
</div>

@endsection

@push('scripts')
@endpush
