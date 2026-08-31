@extends('layouts.app')

@push('styles')
<style>
    .profile-completeness-bar { height: 8px; }
    .missing-fields-list { columns: 2; gap: 1rem; }
    .kpi-card .card-body { padding: 1.25rem; }
    .supplier-profile-section {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--bs-border-color);
    }
    .supplier-profile-section-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        font-size: 1rem;
    }
</style>
@endpush

@section('page_title', $supplier->rfc_name ?? $supplier->commercial_name ?? 'Detalle del proveedor')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">
        @if (auth()->user()?->hasAnyRole('admin|Orden de compra'))
            <a href="{{ route('suppliers.index') }}">Proveedores</a>
        @else
            Proveedores
        @endif
    </li>
    <li class="breadcrumb-item active">{{ $supplier->rfc_name ?? $supplier->commercial_name ?? 'Detalle' }}</li>
@endsection

@section('content')
@php
    $canManageSupplier = auth()->user()?->hasAnyRole('admin|Orden de compra|Pagos');
@endphp

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

@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        {{ session('warning') }}
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
</div>

@if (!$canManageSupplier)
    <div class="alert alert-info">
        <i class="ri-eye-line me-1"></i> Vista en modo solo lectura para el perfil Pagos.
    </div>
@endif


{{-- ══════════════════════════════════════════════════════════════
     FILA 1 — Completitud del perfil + KPI cards
══════════════════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-3">

    {{-- Tarjeta de completitud --}}
    <div class="col-xl-4 col-lg-5">
        <div class="card">
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
    
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-building-line me-1 text-muted"></i> Información general
                </h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalEditSupplier">
                    <i class="ri-edit-line me-1"></i> Editar perfil
                </button>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted fw-normal fs-13">Razón social</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->rfc_name ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Nombre comercial</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->commercial_name ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">RFC</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $supplier->rfc_num ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Domicilio</dt>
                    <dd class="col-sm-7 fw-medium fs-14">
                        {{ implode(', ', array_filter([
                            $supplier->street,
                            $supplier->colony,
                            $supplier->postal_code ? 'CP ' . $supplier->postal_code : null,
                            $supplier->city,
                            $supplier->state,
                        ])) ?: '—' }}
                    </dd>

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

            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-shield-user-line me-1 text-muted"></i> Acceso a Portal de Proveedores
                </h5>
                @if ($canManageSupplier && !$supplier->portal_user_id)
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalPortalAccessConfig">
                        <i class="ri-key-2-line me-1"></i> Habilitar acceso a Portal
                    </button>
                @elseif ($canManageSupplier && $supplier->portal_access_enabled)
                    <form action="{{ route('suppliers.portal_access.disable', $supplier) }}" method="POST"
                        onsubmit="return confirm('¿Deshabilitar acceso al Portal para este proveedor?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="ri-lock-line me-1"></i> Deshabilitar acceso
                        </button>
                    </form>
                @elseif ($canManageSupplier)
                    <form action="{{ route('suppliers.portal_access.reactivate', $supplier) }}" method="POST"
                        onsubmit="return confirm('¿Reactivar acceso al Portal para este proveedor?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="ri-lock-unlock-line me-1"></i> Reactivar acceso
                        </button>
                    </form>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted fs-12 mb-1">Estatus de acceso</div>
                        @if (!$supplier->portal_user_id)
                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Sin configurar</span>
                        @elseif ($supplier->portal_access_enabled)
                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activo</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Deshabilitado</span>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted fs-12 mb-1">Correo portal</div>
                        <div class="fw-medium fs-14">{{ $supplier->portalUser?->email ?? '—' }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted fs-12 mb-1">Última activación</div>
                        <div class="fw-medium fs-14">{{ $supplier->portal_access_activated_at?->format('d/m/Y H:i') ?? '—' }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted fs-12 mb-1">Última desactivación</div>
                        <div class="fw-medium fs-14">{{ $supplier->portal_access_deactivated_at?->format('d/m/Y H:i') ?? '—' }}</div>
                    </div>
                </div>

                @if ($canManageSupplier)
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalPortalAccessConfig">
                        <i class="ri-settings-3-line me-1"></i> Configurar credenciales del portal
                    </button>
                </div>
                @endif
            </div>
        </div>

        <hr class="my-4">

        <div class="card kpi-card">
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

        <div class="card kpi-card">
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

        <div class="card kpi-card">
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

        <div class="card kpi-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div class="avatar-sm bg-info bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                        <i class="ri-receipt-line text-info fs-18"></i>
                    </div>
                    <span class="text-muted fs-12 fw-medium">Vales de material</span>
                </div>
                <h4 class="fw-bold mb-0">{{ $supplier->material_vouchers_count ?? 0 }}</h4>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="row">
            {{-- Tarjeta de Contactos --}}
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="ri-contacts-line me-1 text-muted"></i> Contactos
                        </h5>
                        @if ($canManageSupplier)
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateContact">
                            <i class="ri-add-line me-1"></i> Registrar nuevo
                        </button>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if($supplier->contacts->isEmpty())
                            <p class="text-center text-muted fs-13 py-4 mb-0">
                                <i class="ri-contacts-line fs-24 d-block mb-1 opacity-50"></i>
                                Sin contactos registrados.
                            </p>
                        @else
                            @php
                                $avatarColors = ['primary', 'success', 'danger', 'warning', 'info', 'secondary'];
                            @endphp
                            <div class="table-responsive">
                                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th>Contacto</th>
                                            <th>Teléfono</th>
                                            <th>Correo electrónico</th>
                                            <th>Principal</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($supplier->contacts as $contact)
                                            @php
                                                $colorIdx = abs(crc32($contact->name)) % count($avatarColors);
                                                $color    = $avatarColors[$colorIdx];
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="rounded-circle bg-{{ $color }} bg-opacity-15 d-flex align-items-center justify-content-center text-{{ $color }} fw-bold flex-shrink-0"
                                                            style="width:36px;height:36px;font-size:14px;">
                                                            <span class="text-white">{{ strtoupper(substr($contact->name, 0, 1)) }}</span>
                                                        </div>
                                                        <span class="fw-medium fs-14">{{ $contact->name }}</span>
                                                    </div>
                                                </td>
                                                <td class="fs-13 text-muted">{{ $contact->phone ?? '—' }}</td>
                                                <td class="fs-13 text-muted">{{ $contact->email ?? '—' }}</td>
                                                <td>
                                                    @if($contact->is_primary)
                                                        <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">
                                                            <i class="ri-star-fill me-1"></i> Principal
                                                        </span>
                                                    @else
                                                        <span class="text-muted fs-12">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($canManageSupplier)
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-light btn-edit-contact"
                                                            data-name="{{ $contact->name }}"
                                                            data-phone="{{ $contact->phone }}"
                                                            data-email="{{ $contact->email }}"
                                                            data-is-primary="{{ $contact->is_primary ? '1' : '0' }}"
                                                            data-url="{{ route('supplier_contacts.update', [$supplier, $contact]) }}">
                                                            <i class="ri-edit-line fs-14"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-light btn-delete-contact"
                                                            data-name="{{ $contact->name }}"
                                                            data-url="{{ route('supplier_contacts.destroy', [$supplier, $contact]) }}">
                                                            <i class="ri-delete-bin-line fs-14 text-danger"></i>
                                                        </button>
                                                    </div>
                                                    @else
                                                        <span class="text-muted fs-12">Solo lectura</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tarjeta de datos bancarios --}}
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="ri-bank-line me-1 text-muted"></i> Datos Bancarios
                        </h5>
                        @if ($canManageSupplier)
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateLocation">
                            <i class="ri-add-line me-1"></i> Registrar nueva
                        </button>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        @if($supplier->locations->isEmpty())
                            <p class="text-center text-muted fs-13 py-4 mb-0">
                                <i class="ri-bank-line fs-24 d-block mb-1 opacity-50"></i>
                                Sin datos bancarios registrados.
                            </p>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach($supplier->locations as $location)
                                    <li class="list-group-item px-3 py-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div style="min-width:0;">
                                                <p class="fw-semibold fs-14 mb-0">{{ $location->name }}</p>
                                                @if($location->bank_name || $location->bank_account || $location->bank_clabe || $location->currency || $location->account_statement_path)
                                                    <div class="mt-1 d-flex flex-wrap gap-2">
                                                        @if($location->bank_name)
                                                            <small class="text-muted fs-12"><i class="ri-bank-line me-1"></i>{{ $location->bank_name }}</small>
                                                        @endif
                                                        @if($location->currency)
                                                            <span class="badge bg-info-subtle text-info py-0 px-2 fs-11">{{ $location->currency }}</span>
                                                        @endif
                                                        @if($location->bank_account)
                                                            <small class="text-muted fs-12">Cta: <span class="text-dark fw-medium">{{ $location->bank_account }}</span></small>
                                                        @endif
                                                        @if($location->bank_clabe)
                                                            <small class="text-muted fs-12">CLABE: <span class="text-dark fw-medium">{{ $location->bank_clabe }}</span></small>
                                                        @endif
                                                        @if($location->account_statement_path)
                                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($location->account_statement_path) }}"
                                                               target="_blank" class="text-primary fs-12">
                                                                <i class="ri-file-text-line me-1"></i>Carátula de estado de cuenta
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="d-flex gap-1 flex-shrink-0 ms-3">
                                                @if ($canManageSupplier)
                                                <button type="button" class="btn btn-icon btn-sm btn-light btn-edit-location"
                                                    data-name="{{ $location->name }}"
                                                    data-bank-name="{{ $location->bank_name }}"
                                                    data-bank-account="{{ $location->bank_account }}"
                                                    data-bank-clabe="{{ $location->bank_clabe }}"
                                                    data-currency="{{ $location->currency }}"
                                                    data-url="{{ route('supplier_locations.update', [$supplier, $location]) }}"
                                                    title="Editar">
                                                    <i class="ri-edit-2-line fs-13"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-sm btn-light btn-delete-location"
                                                    data-name="{{ $location->name }}"
                                                    data-url="{{ route('supplier_locations.destroy', [$supplier, $location]) }}"
                                                    title="Eliminar">
                                                    <i class="ri-delete-bin-line fs-13 text-danger"></i>
                                                </button>
                                                @else
                                                    <span class="text-muted fs-12">Solo lectura</span>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

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
                                            #{{ $order->folio }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fs-13">{{ $tipoLabel }}</span>
                                        @php
                                            $projectName = $order->projectRelation?->name ?? $order->project;
                                            $siteName    = $order->workRelation?->name ?? $order->site;
                                        @endphp
                                        @if ($projectName || $siteName)
                                            <small class="text-muted d-block fs-11">
                                                {{ implode(' · ', array_filter([$projectName, $siteName])) }}
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

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-receipt-line me-1 text-muted"></i> Vales de material
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Estatus</th>
                                <th>Renglones</th>
                                <th>Proyecto / Obra</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $voucherStatusMap = [
                                    'emitido'    => ['label' => 'Emitido', 'class' => 'bg-warning-subtle text-warning'],
                                    'autorizado' => ['label' => 'Autorizado', 'class' => 'bg-info-subtle text-info'],
                                    'completado' => ['label' => 'Completado', 'class' => 'bg-primary-subtle text-primary'],
                                    'facturado'  => ['label' => 'Facturado', 'class' => 'bg-secondary-subtle text-secondary'],
                                    'pagado'     => ['label' => 'Pagado', 'class' => 'bg-success-subtle text-success'],
                                ];
                            @endphp
                            @forelse ($supplier->materialVouchers as $voucher)
                                @php
                                    $vs = $voucherStatusMap[$voucher->status] ?? ['label' => $voucher->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $voucher->folio }}</td>
                                    <td>{{ $voucher->voucher_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td><span class="badge {{ $vs['class'] }} py-1 px-2 fs-12">{{ $vs['label'] }}</span></td>
                                    <td>{{ $voucher->items_count }}</td>
                                    <td>
                                        <span class="d-block">{{ $voucher->project?->name ?? '—' }}</span>
                                        @if($voucher->projectWork)
                                            <small class="text-muted">{{ $voucher->projectWork->name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('material_vouchers.show', $voucher) }}" class="btn btn-light btn-sm" title="Ver detalle">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-receipt-line fs-24 d-block mb-1 opacity-50"></i>
                                        Sin vales de material registrados aún.
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

@if ($canManageSupplier)
{{-- ══════════════════════════════════════════════════════════════
     MODAL — Configurar acceso portal proveedor
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalPortalAccessConfig" tabindex="-1" aria-labelledby="modalPortalAccessConfigLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPortalAccessConfigLabel">
                    <i class="ri-shield-user-line me-1"></i> Configurar acceso al portal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('suppliers.portal_access.enable', $supplier) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="border rounded-2 bg-light-subtle p-3 mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-sm bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-building-line fs-18"></i>
                            </span>
                            <div>
                                <div class="text-muted fs-12">Proveedor</div>
                                <div class="fw-semibold">{{ $supplier->commercial_name ?? $supplier->rfc_name }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="supplier-profile-section">
                                <span class="supplier-profile-section-icon bg-primary-subtle text-primary">
                                    <i class="ri-user-settings-line"></i>
                                </span>
                                <h6 class="mb-0 fw-semibold">Identidad de acceso</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="portalName" class="form-label fw-medium">Nombre de usuario</label>
                            <input type="text" name="portal_name" id="portalName"
                                   class="form-control @error('portal_name') is-invalid @enderror"
                                   value="{{ old('portal_name', $supplier->portalUser?->name ?? ($supplier->commercial_name ?? $supplier->rfc_name)) }}"
                                   placeholder="Ej. Compras Proveedor SA">
                            @error('portal_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="portalEmail" class="form-label fw-medium">Correo de acceso <span class="text-danger">*</span></label>
                            <input type="email" name="portal_email" id="portalEmail"
                                   class="form-control @error('portal_email') is-invalid @enderror"
                                   value="{{ old('portal_email', $supplier->portalUser?->email ?? $supplier->email) }}"
                                   placeholder="portal.proveedor@empresa.com" required>
                            @error('portal_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="supplier-profile-section">
                                <span class="supplier-profile-section-icon bg-success-subtle text-success">
                                    <i class="ri-lock-password-line"></i>
                                </span>
                                <div>
                                    <h6 class="mb-0 fw-semibold">Credenciales de acceso</h6>
                                    @if ($supplier->portal_user_id)
                                        <p class="mb-0 text-muted fs-12">Deja ambos campos vacíos para conservar la contraseña actual.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="portalPassword" class="form-label fw-medium">
                                Contraseña
                                @if (!$supplier->portal_user_id)
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <input type="password" name="portal_password" id="portalPassword"
                                   class="form-control @error('portal_password') is-invalid @enderror"
                                   placeholder="Mínimo 8 caracteres" autocomplete="new-password" @if (!$supplier->portal_user_id) required @endif>
                            @error('portal_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="portalPasswordConfirmation" class="form-label fw-medium">
                                Confirmar contraseña
                                @if (!$supplier->portal_user_id)
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <input type="password" name="portal_password_confirmation" id="portalPasswordConfirmation"
                                   class="form-control" placeholder="Repite la contraseña" autocomplete="new-password"
                                   @if (!$supplier->portal_user_id) required @endif>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Guardar configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div class="row">
    <div class="col-12">
        @include('layouts.utilities._log_table', [
            'model_type' => 'Supplier',
            'model_id'   => $supplier->id,
        ])
    </div>
</div>

@if ($canManageSupplier)
{{-- ══════════════════════════════════════════════════════════════
     MODAL — Editar información general del proveedor
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalEditSupplier" tabindex="-1" aria-labelledby="modalEditSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditSupplierLabel">
                    <i class="ri-building-line me-1"></i> Editar información general
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('suppliers.update_info', $supplier) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="supplier-profile-section">
                                <span class="supplier-profile-section-icon bg-primary-subtle text-primary">
                                    <i class="ri-building-line"></i>
                                </span>
                                <h6 class="mb-0 fw-semibold">Información general</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="editSupplierRfcName" class="form-label fw-medium">Razón social <span class="text-danger">*</span></label>
                            <input type="text" name="rfc_name" id="editSupplierRfcName"
                                   class="form-control @error('rfc_name') is-invalid @enderror"
                                   value="{{ old('rfc_name', $supplier->rfc_name) }}" required>
                            @error('rfc_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="editSupplierCommercialName" class="form-label fw-medium">Nombre comercial</label>
                            <input type="text" name="commercial_name" id="editSupplierCommercialName"
                                   class="form-control @error('commercial_name') is-invalid @enderror"
                                   value="{{ old('commercial_name', $supplier->commercial_name) }}">
                            @error('commercial_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="editSupplierRfc" class="form-label fw-medium">RFC</label>
                            <input type="text" name="rfc_num" id="editSupplierRfc" maxlength="13"
                                   class="form-control @error('rfc_num') is-invalid @enderror"
                                   value="{{ old('rfc_num', $supplier->rfc_num) }}"
                                   style="text-transform:uppercase;">
                            @error('rfc_num')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="editSupplierStatus" class="form-label fw-medium">Estatus</label>
                            <select name="status" id="editSupplierStatus" class="form-select @error('status') is-invalid @enderror">
                                <option value="">Sin definir</option>
                                <option value="active" {{ old('status', $supplier->status) === 'active' ? 'selected' : '' }}>Activo</option>
                                <option value="inactive" {{ old('status', $supplier->status) === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                <option value="blacklisted" {{ old('status', $supplier->status) === 'blacklisted' ? 'selected' : '' }}>Bloqueado</option>
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="supplier-profile-section">
                                <span class="supplier-profile-section-icon bg-info-subtle text-info">
                                    <i class="ri-map-pin-line"></i>
                                </span>
                                <h6 class="mb-0 fw-semibold">Domicilio fiscal</h6>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label for="editSupplierStreet" class="form-label fw-medium">Calle</label>
                            <input type="text" name="street" id="editSupplierStreet"
                                   class="form-control @error('street') is-invalid @enderror"
                                   value="{{ old('street', $supplier->street) }}">
                            @error('street')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="editSupplierPostalCode" class="form-label fw-medium">Código Postal</label>
                            <input type="text" name="postal_code" id="editSupplierPostalCode"
                                   class="form-control @error('postal_code') is-invalid @enderror"
                                   value="{{ old('postal_code', $supplier->postal_code) }}">
                            @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="editSupplierColony" class="form-label fw-medium">Colonia</label>
                            <input type="text" name="colony" id="editSupplierColony"
                                   class="form-control @error('colony') is-invalid @enderror"
                                   value="{{ old('colony', $supplier->colony) }}">
                            @error('colony')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="editSupplierCity" class="form-label fw-medium">Ciudad</label>
                            <input type="text" name="city" id="editSupplierCity"
                                   class="form-control @error('city') is-invalid @enderror"
                                   value="{{ old('city', $supplier->city) }}">
                            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="editSupplierState" class="form-label fw-medium">Estado</label>
                            <input type="text" name="state" id="editSupplierState"
                                   class="form-control @error('state') is-invalid @enderror"
                                   value="{{ old('state', $supplier->state) }}">
                            @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     MODALES — Contactos
══════════════════════════════════════════════════════════════════ --}}

{{-- Crear contacto --}}
<div class="modal fade" id="modalCreateContact" tabindex="-1" aria-labelledby="modalCreateContactLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateContactLabel">
                    <i class="ri-contacts-line me-1"></i> Registrar contacto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('supplier_contacts.store', $supplier) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium fs-13">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Nombre del contacto" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium fs-13">Teléfono</label>
                        <input type="text" name="phone" class="form-control" placeholder="+52 55 0000 0000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium fs-13">Correo electrónico</label>
                        <input type="email" name="email" class="form-control" placeholder="contacto@empresa.com">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="createIsPrimary">
                        <label class="form-check-label fs-13" for="createIsPrimary">Contacto principal</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar contacto</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Editar contacto --}}
<div class="modal fade" id="modalEditContact" tabindex="-1" aria-labelledby="modalEditContactLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditContactLabel">
                    <i class="ri-edit-2-line me-1"></i> Editar contacto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditContact" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium fs-13">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editContactName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium fs-13">Teléfono</label>
                        <input type="text" name="phone" id="editContactPhone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium fs-13">Correo electrónico</label>
                        <input type="email" name="email" id="editContactEmail" class="form-control">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="editIsPrimary">
                        <label class="form-check-label fs-13" for="editIsPrimary">Contacto principal</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Eliminar contacto --}}
<div class="modal fade" id="modalDeleteContact" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="ri-delete-bin-line fs-36 text-danger mb-2 d-block"></i>
                <h5 class="mb-1">¿Eliminar contacto?</h5>
                <p class="text-muted fs-13 mb-0">Se eliminará permanentemente a <strong id="deleteContactName"></strong>.</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <form id="formDeleteContact" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
    MODALES — Datos bancarios
══════════════════════════════════════════════════════════════════ --}}

{{-- Registrar datos bancarios --}}
<div class="modal fade" id="modalCreateLocation" tabindex="-1" aria-labelledby="modalCreateLocationLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateLocationLabel">
                    <i class="ri-bank-line me-1"></i> Registrar Datos Bancarios
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('supplier_locations.store', $supplier) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium fs-13">Nombre de la cuenta <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Ej. Cuenta principal" required>
                    </div>
                    <hr class="my-3">
                    <p class="fs-12 fw-semibold text-muted text-uppercase mb-3">Datos bancarios</p>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-medium fs-13">Banco</label>
                            <input type="text" name="bank_name" class="form-control">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-medium fs-13">Moneda</label>
                            <select name="currency" class="form-select">
                                <option value="">— Seleccionar —</option>
                                <option value="MXN">MXN</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-medium fs-13">Cuenta</label>
                            <input type="text" name="bank_account" class="form-control">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-medium fs-13">CLABE Interbancaria</label>
                            <input type="text" name="bank_clabe" class="form-control" maxlength="18">
                        </div>
                        <div class="col-sm-12">
                            <label class="form-label fw-medium fs-13">Carátula de estado de cuenta</label>
                            <input type="file" name="account_statement" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text">PDF, JPG o PNG. Tamaño máximo: 10 MB.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar datos bancarios</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Editar datos bancarios --}}
<div class="modal fade" id="modalEditLocation" tabindex="-1" aria-labelledby="modalEditLocationLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditLocationLabel">
                    <i class="ri-edit-2-line me-1"></i> Editar Datos Bancarios
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditLocation" action="" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium fs-13">Nombre de la cuenta <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editLocationName" class="form-control" required>
                    </div>
                    <hr class="my-3">
                    <p class="fs-12 fw-semibold text-muted text-uppercase mb-3">Datos bancarios</p>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-medium fs-13">Banco</label>
                            <input type="text" name="bank_name" id="editLocationBankName" class="form-control">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-medium fs-13">Moneda</label>
                            <select name="currency" id="editLocationCurrency" class="form-select">
                                <option value="">— Seleccionar —</option>
                                <option value="MXN">MXN</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-medium fs-13">Cuenta</label>
                            <input type="text" name="bank_account" id="editLocationBankAccount" class="form-control">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-medium fs-13">CLABE Interbancaria</label>
                            <input type="text" name="bank_clabe" id="editLocationBankClabe" class="form-control" maxlength="18">
                        </div>
                        <div class="col-sm-12">
                            <label class="form-label fw-medium fs-13">Carátula de estado de cuenta</label>
                            <input type="file" name="account_statement" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text">Selecciona un archivo para reemplazar la carátula actual.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Eliminar datos bancarios --}}
<div class="modal fade" id="modalDeleteLocation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="ri-delete-bin-line fs-36 text-danger mb-2 d-block"></i>
                <h5 class="mb-1">¿Eliminar datos bancarios?</h5>
                <p class="text-muted fs-13 mb-0">Se eliminará permanentemente <strong id="deleteLocationName"></strong>.</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <form id="formDeleteLocation" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Inicializar tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // RFC a mayúsculas en el modal de info general
    document.getElementById('editSupplierRfc')?.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });

    // ── Contactos ────────────────────────────────────────────────────────────

    document.querySelectorAll('.btn-edit-contact').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const form = document.getElementById('formEditContact');
            form.action = this.dataset.url;
            document.getElementById('editContactName').value   = this.dataset.name  || '';
            document.getElementById('editContactPhone').value  = this.dataset.phone || '';
            document.getElementById('editContactEmail').value  = this.dataset.email || '';
            document.getElementById('editIsPrimary').checked   = this.dataset.isPrimary === '1';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditContact')).show();
        });
    });

    document.querySelectorAll('.btn-delete-contact').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('deleteContactName').textContent = this.dataset.name;
            document.getElementById('formDeleteContact').action = this.dataset.url;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDeleteContact')).show();
        });
    });

    // ── Datos bancarios ───────────────────────────────────────────────────────

    document.querySelectorAll('.btn-edit-location').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const d    = this.dataset;
            const form = document.getElementById('formEditLocation');
            form.action = d.url;
            document.getElementById('editLocationName').value        = d.name        || '';
            document.getElementById('editLocationBankName').value    = d.bankName    || '';
            document.getElementById('editLocationBankAccount').value = d.bankAccount || '';
            document.getElementById('editLocationBankClabe').value   = d.bankClabe   || '';
            const sel = document.getElementById('editLocationCurrency');
            sel.value = d.currency || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditLocation')).show();
        });
    });

    document.querySelectorAll('.btn-delete-location').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('deleteLocationName').textContent = this.dataset.name;
            document.getElementById('formDeleteLocation').action = this.dataset.url;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDeleteLocation')).show();
        });
    });

    const portalConfigModalEl = document.getElementById('modalPortalAccessConfig');
    const shouldOpenPortalConfig = @json($errors->hasAny(['portal_name', 'portal_email', 'portal_password']) || request()->boolean('setup_portal'));
    if (portalConfigModalEl && shouldOpenPortalConfig) {
        bootstrap.Modal.getOrCreateInstance(portalConfigModalEl).show();
    }
});
</script>
@endpush
