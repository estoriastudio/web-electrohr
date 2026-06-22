@extends('layouts.app')

@section('page_title', 'Almacén — Pila SOLMAT')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Pila SOLMAT (Almacén)</li>
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
                        <i class="ri-inbox-2-line me-2 text-warning"></i>Pila SOLMAT — Almacén
                    </h4>
                    <p class="text-muted fs-12 mb-0 mt-1">
                        Solicitudes de Material enviadas a Almacén, listas para generar SOLCOM.
                    </p>
                </div>
                <a href="{{ route('material_requests.index') }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-left-line me-1"></i>Ver todas las SOLMAT
                </a>
            </div>

            {{-- Búsqueda --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('warehouse.solmat_pile') }}" class="row g-2 align-items-end">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control" placeholder="Folio, proyecto, zona…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search)
                            <a href="{{ route('warehouse.solmat_pile') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
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
                                <th>Folio</th>
                                <th>Proyecto</th>
                                <th>Obras</th>
                                <th>Ubicación</th>
                                <th>Categoría</th>
                                <th>F. Solicitud</th>
                                <th>Ítems</th>
                                <th>Elaborada por</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($materialRequests as $mr)
                                <tr>
                                    <td>
                                        <a href="{{ route('material_requests.show', $mr) }}"
                                           class="fw-semibold text-primary text-decoration-none">
                                            #{{ $mr->folio }}
                                        </a>
                                    </td>
                                    <td>{{ $mr->project?->name ?? '—' }}</td>
                                    <td class="w-25">
                                        <div class="d-flex flex-wrap gap-1" style="max-width:260px; white-space:normal;">
                                            @foreach ($mr->projectWorks->take(2) as $pw)
                                                <span class="badge bg-info-subtle text-info border py-1 px-2 fs-12" style="text-align: left; white-space:initial;">{{ $pw->name }}</span>
                                            @endforeach
                                            @if ($mr->projectWorks->count() > 2)
                                                <span class="badge bg-secondary-subtle text-secondary border py-1 px-2 fs-12" style="text-align: left; white-space:initial;">
                                                    +{{ $mr->projectWorks->count() - 2 }} más
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $mr->zone }}</td>
                                    <td>{{ $mr->supply_category }}</td>
                                    <td>{{ $mr->request_date?->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                            {{ $mr->items->count() }} ítem(s)
                                        </span>
                                    </td>
                                    <td>{{ $mr->requestedBy?->name ?? '—' }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('material_requests.show', $mr) }}"
                                               class="btn btn-light btn-sm" title="Ver SOLMAT">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <a href="{{ route('purchase_requests.create_from_solmat', $mr) }}"
                                               class="btn btn-warning btn-sm" title="Crear SOLCOM">
                                                <i class="ri-shopping-cart-2-line me-1"></i>Crear SOLCOM
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="ri-inbox-2-line fs-24 d-block mb-2 opacity-50"></i>
                                        No hay SOLMAT en espera de procesamiento.<br>
                                        <small>Las SOLMAT enviadas a Almacén aparecerán aquí.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($materialRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $materialRequests->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
