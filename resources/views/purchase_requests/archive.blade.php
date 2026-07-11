@extends('layouts.app')

@section('page_title', 'Solicitudes de Compra — Archivadas')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_requests.index') }}">Solicitudes de Compra</a></li>
    <li class="breadcrumb-item active">Archivadas</li>
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
                    <h4 class="card-title mb-0">
                        <i class="ri-archive-line me-2 text-muted"></i>SOLCOM archivadas
                    </h4>
                    <small class="text-muted">Estas solicitudes no aparecen en el listado activo.</small>
                </div>
                <a href="{{ route('purchase_requests.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Volver al listado activo
                </a>
            </div>

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('purchase_requests.archived') }}" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por folio, proyecto o zona..."
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search)
                            <a href="{{ route('purchase_requests.archived') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @include('purchase_requests.utilities._table')
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
