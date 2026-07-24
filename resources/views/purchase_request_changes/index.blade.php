@extends('layouts.app')

@section('page_title', 'Solicitudes de Cambio — SOLCOM')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_requests.index') }}">Solicitudes de Compra</a></li>
    <li class="breadcrumb-item active">Solicitudes de Cambio</li>
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

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-medium fs-13 mb-1">Cambios pendientes</p>
                        <h3 class="mb-0 fw-bold">{{ $pendingCount }}</h3>
                        <p class="text-muted fs-12 mb-0">Solicitudes activas por atender</p>
                    </div>
                    <div class="bg-warning-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-edit-circle-line fs-24 text-warning"></i>
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
                        <p class="text-muted fw-medium fs-13 mb-1">SOLCOM afectadas</p>
                        <h3 class="mb-0 fw-bold">{{ $affectedSolcomCount }}</h3>
                        <p class="text-muted fs-12 mb-0">Documentos con seguimiento abierto</p>
                    </div>
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-file-list-3-line fs-24 text-primary"></i>
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
                        <p class="text-muted fw-medium fs-13 mb-1">Urgentes</p>
                        <h3 class="mb-0 fw-bold {{ $urgentCount > 0 ? 'text-danger' : '' }}">{{ $urgentCount }}</h3>
                        <p class="text-muted fs-12 mb-0">Con necesidad en 5 días o menos</p>
                    </div>
                    <div class="bg-danger-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-alarm-warning-line fs-24 text-danger"></i>
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
                        <p class="text-muted fw-medium fs-13 mb-1">Sin comprador</p>
                        <h3 class="mb-0 fw-bold {{ $unassignedCount > 0 ? 'text-warning' : '' }}">{{ $unassignedCount }}</h3>
                        <p class="text-muted fs-12 mb-0">Cambios en SOLCOM sin asignación</p>
                    </div>
                    <div class="bg-secondary-subtle rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;">
                        <i class="ri-user-unfollow-line fs-24 text-secondary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">
                        <i class="ri-task-line me-2 text-warning"></i>Panel de Solicitudes de Cambio
                    </h4>
                    <p class="text-muted fs-12 mb-0 mt-1">
                        Seguimiento operativo de cambios solicitados sobre SOLCOMs pendientes de resolver.
                    </p>
                </div>
                <a href="{{ route('purchase_requests.index', ['status' => 'changes_requested']) }}" class="btn btn-light btn-sm">
                    <i class="ri-shopping-cart-2-line me-1"></i>Ver SOLCOMs
                </a>
            </div>

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('purchase_request_changes.index') }}" class="row g-2 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label form-label-sm mb-1">Buscar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Folio, proyecto, descripción, zona o texto del cambio…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label form-label-sm mb-1">Comprador asignado</label>
                        <select name="assigned_to" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach ($purchasingUsers as $user)
                                <option value="{{ $user->id }}" {{ (string) $assignedTo === (string) $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label form-label-sm mb-1">Solicitada por</label>
                        <select name="requested_by" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach ($requesterUsers as $user)
                                <option value="{{ $user->id }}" {{ (string) $requestedBy === (string) $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search !== '' || $assignedTo !== '' || $requestedBy !== '')
                            <a href="{{ route('purchase_request_changes.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>SOLCOM</th>
                                <th>SOLMAT</th>
                                <th>Proyecto / Obra</th>
                                <th>Descripción</th>
                                <th>Comprador</th>
                                <th>Necesidad</th>
                                <th>Solicitada por</th>
                                <th>Fecha cambio</th>
                                <th>Cambio requerido</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($changeNotes as $note)
                                @php
                                    $purchaseRequest = $note->purchaseRequest;
                                    $projectName = $purchaseRequest?->project?->name;
                                    $workNames = $purchaseRequest?->projectWorks?->pluck('name')->implode(' · ');
                                    if ($workNames === '' && $purchaseRequest?->projectWork) {
                                        $workNames = $purchaseRequest->projectWork->name;
                                    }
                                    $needDate = $purchaseRequest?->need_date;
                                    $isUrgent = $needDate && $needDate->lte(now()->addDays(5));
                                @endphp
                                <tr>
                                    <td>
                                        @if ($purchaseRequest)
                                            <a href="{{ route('purchase_requests.show', $purchaseRequest) }}"
                                               class="fw-semibold text-primary text-decoration-none">
                                                #{{ $purchaseRequest->folio }}
                                            </a>
                                            <div class="mt-1">
                                                <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Cambios solicitados</span>
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($purchaseRequest?->materialRequest)
                                            <a href="{{ route('material_requests.show', $purchaseRequest->materialRequest) }}"
                                               class="text-muted fw-semibold text-decoration-none">
                                                #{{ $purchaseRequest->materialRequest->folio }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td style="max-width:220px;">
                                        @if ($projectName)
                                            <div class="hover-marquee" title="{{ $projectName }}">
                                                <span class="track"><span>{{ $projectName }}</span><span aria-hidden="true">{{ $projectName }}</span></span>
                                            </div>
                                        @endif
                                        @if ($workNames)
                                            <small class="text-muted d-block hover-marquee mt-1" title="{{ $workNames }}">
                                                <span class="track"><span>{{ $workNames }}</span><span aria-hidden="true">{{ $workNames }}</span></span>
                                            </small>
                                        @endif
                                        @if (! $projectName && ! $workNames)
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td style="max-width:220px;">
                                        <span class="d-block text-truncate" title="{{ $purchaseRequest?->short_description ?? '—' }}">
                                            {{ $purchaseRequest?->short_description ?? '—' }}
                                        </span>
                                        @if ($purchaseRequest?->zone)
                                            <small class="text-muted">{{ $purchaseRequest->zone }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($purchaseRequest?->assignedTo)
                                            <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                                <i class="ri-user-line me-1"></i>{{ $purchaseRequest->assignedTo->name }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border py-1 px-2 fs-12">Sin asignar</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($needDate)
                                            <span class="{{ $isUrgent ? 'text-danger fw-medium' : '' }}">
                                                {{ $needDate->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-xs bg-warning-subtle rounded-circle d-flex align-items-center justify-content-center shrink-0">
                                                <span class="text-warning fw-semibold" style="font-size: 10px;">
                                                    {{ strtoupper(substr($note->requestedBy?->name ?? '?', 0, 1)) }}
                                                </span>
                                            </div>
                                            <span class="fs-13">{{ $note->requestedBy?->name ?? 'Usuario' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted fs-12 text-nowrap">{{ $note->created_at->format('d/m/Y H:i') }}</span>
                                        <div><small class="text-muted">{{ $note->created_at->diffForHumans() }}</small></div>
                                    </td>
                                    <td style="min-width:280px; max-width:380px;">
                                        <p class="mb-0 text-body fs-13">{{ $note->text }}</p>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            @if ($purchaseRequest)
                                                <a href="{{ route('purchase_requests.show', $purchaseRequest) }}"
                                                   class="btn btn-light btn-sm"
                                                   title="Ver detalle">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                @hasanyrole('admin|Solcom')
                                                <form action="{{ route('purchase_requests.change_notes.resolve', [$purchaseRequest, $note]) }}"
                                                      method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm" title="Marcar como resuelta">
                                                        <i class="ri-check-line"></i>
                                                    </button>
                                                </form>
                                                @endhasanyrole
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-24 d-block mb-2 opacity-50"></i>
                                        No hay solicitudes de cambio pendientes en este momento.<br>
                                        <small>Cuando Compras solicite ajustes sobre una SOLCOM, aparecerán aquí.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($changeNotes->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $changeNotes->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection