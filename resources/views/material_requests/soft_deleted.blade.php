@extends('layouts.app')

@section('page_title', 'SOLMAT — Papelera')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('material_requests.index') }}">Solicitudes de Material</a></li>
    <li class="breadcrumb-item active">Papelera</li>
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
        <div class="card border-danger">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom bg-danger-subtle">
                <div>
                    <h4 class="card-title mb-0 text-danger">
                        <i class="ri-delete-bin-line me-2"></i>Papelera — SOLMATs eliminadas
                    </h4>
                    <small class="text-muted">Solo los administradores pueden eliminar permanentemente o restaurar estos registros.</small>
                </div>
                <a href="{{ route('material_requests.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Volver al listado activo
                </a>
            </div>

            {{-- Barra de búsqueda --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('material_requests.soft_deleted') }}" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por folio, proyecto, zona o categoría…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search)
                            <a href="{{ route('material_requests.soft_deleted') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @include('material_requests.utilities._table')
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
