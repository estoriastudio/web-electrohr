@extends('layouts.app')

@section('page_title', 'Mi Pila SOLCOM — Compras')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Pila SOLCOM (Compras)</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">
                        <i class="ri-stack-line me-2 text-primary"></i>Mi Pila SOLCOM
                    </h4>
                    <p class="text-muted fs-12 mb-0 mt-1">
                        SOLCOMs asignadas a ti para generar Órdenes de Compra.
                    </p>
                </div>
                <a href="{{ route('purchase_requests.index') }}" class="btn btn-light btn-sm">
                    <i class="ri-list-check me-1"></i>Ver todas las SOLCOM
                </a>
            </div>

            {{-- Búsqueda --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('purchasing.solcom_pile') }}" class="row g-2 align-items-end">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control" placeholder="Folio, proyecto, descripción…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search)
                            <a href="{{ route('purchasing.solcom_pile') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>SOLMAT</th>
                                <th>Proyecto</th>
                                <th>Obra</th>
                                <th>Descripción</th>
                                <th>F. Necesidad</th>
                                <th>Ítems</th>
                                <th>Notas Pendientes</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchaseRequests as $pr)
                                @php
                                    $pendingNotes = $pr->changeNotes->whereNull('resolved_at')->count();
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('purchase_requests.show', $pr) }}"
                                           class="fw-semibold text-primary text-decoration-none">
                                            #{{ $pr->folio }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($pr->materialRequest)
                                            <a href="{{ route('material_requests.show', $pr->materialRequest) }}"
                                               class="text-muted fw-semibold text-decoration-none">
                                                #{{ $pr->materialRequest->folio }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $pr->project?->name ?? '—' }}</td>
                                    <td>{{ $pr->projectWork?->name ?? '—' }}</td>
                                    <td>
                                        <span style="max-width:200px;overflow:hidden;text-overflow:ellipsis;display:block;">
                                            {{ $pr->short_description }}
                                        </span>
                                    </td>
                                    <td>{{ $pr->need_date?->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                            {{ $pr->items->count() ?? 0 }} ítem(s)
                                        </span>
                                    </td>
                                    <td>
                                        @if ($pendingNotes > 0)
                                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">
                                                <i class="ri-edit-circle-line me-1"></i>{{ $pendingNotes }} nota(s)
                                            </span>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('purchase_requests.show', $pr) }}"
                                               class="btn btn-light btn-sm" title="Ver detalle">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            {{-- Botón crear OC solo si no tiene notas pendientes --}}
                                            @if ($pendingNotes === 0)
                                                @hasanyrole('admin|orders')
                                                <a href="{{ route('purchase_orders.create_from_solcom', $pr) }}"
                                                   class="btn btn-primary btn-sm" title="Crear OC vinculada a esta SOLCOM">
                                                    <i class="ri-file-list-3-line me-1"></i>Crear OC
                                                </a>
                                                @endhasanyrole
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="ri-stack-line fs-24 d-block mb-2 opacity-50"></i>
                                        No tienes SOLCOM asignadas en este momento.<br>
                                        <small>Las SOLCOM enviadas a tu usuario aparecerán aquí.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($purchaseRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $purchaseRequests->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
