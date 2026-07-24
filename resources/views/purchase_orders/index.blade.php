@extends('layouts.app')

@section('page_title', 'Órdenes de Compra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Órdenes de Compra</li>
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

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Listado de órdenes de compra</h4>
                </div>
                <div>
                    @hasanyrole('admin|Orden de compra')
                    @can('create')
                    <a href="{{ route('purchase_orders.create') }}" class="btn btn-sm btn-primary">
                        <i class="ri-add-line me-1"></i> Nueva orden de compra
                    </a>
                    @endcan
                    @endhasanyrole
                    <a href="{{ route('concepts.awarded_prices') }}" class="btn btn-sm btn-soft-info ms-1" title="Precios adjudicados">
                        <i class="ri-price-tag-3-line me-1"></i> Precios adjudicados
                    </a>
                    @hasanyrole('admin|Pagos|Orden de compra')
                    <a href="{{ route('purchase_orders.archived') }}" class="btn btn-sm btn-outline-secondary ms-1" title="Ver archivadas">
                        <i class="ri-archive-line me-1"></i> Archivadas
                    </a>
                    @endhasanyrole
                    @hasrole('admin')
                    <a href="{{ route('purchase_orders.soft_deleted') }}" class="btn btn-sm btn-outline-danger ms-1" title="Papelera">
                        <i class="ri-delete-bin-line me-1"></i> Papelera
                    </a>
                    @endhasrole
                </div>
            </div>

            {{-- Barra de filtros --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('purchase_orders.index') }}" class="row g-2 align-items-end">
                    {{-- Búsqueda por proveedor --}}
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por proveedor…"
                                   autocomplete="off">
                        </div>
                    </div>

                    {{-- Filtro por tipo --}}
                    <div class="col-md-3">
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">Todos los tipos</option>
                            <option value="materiales_servicios" {{ $tipo === 'materiales_servicios' ? 'selected' : '' }}>Materiales / Servicios</option>
                            <option value="mantenimiento" {{ $tipo === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                        </select>
                    </div>

                    {{-- Ordenar por vencimiento --}}
                    <div class="col-md-2">
                        <select name="sort_due" class="form-select form-select-sm">
                            <option value="">Más recientes primero</option>
                            <option value="asc" {{ $sortDue === 'asc' ? 'selected' : '' }}>Vence próximo primero</option>
                            <option value="desc" {{ $sortDue === 'desc' ? 'selected' : '' }}>Vence más tarde primero</option>
                        </select>
                    </div>

                    {{-- Acciones --}}
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search || $tipo || $sortDue)
                            <a href="{{ route('purchase_orders.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @include('purchase_orders.utilities._table')
            </div>

            @if ($orders->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection

