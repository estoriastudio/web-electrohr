@extends('layouts.app')

@section('page_title', 'Autorización de Pagos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Autorización de Pagos</li>
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
                    <h4 class="card-title mb-0">Pagos pendientes de autorización</h4>
                    <p class="text-muted fs-13 mb-0">Los pagos con vencimiento en los próximos 7 días aparecen primero.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('payments.interactive') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-file-list-3-line me-1"></i> Activar Modo Interactivo
                    </a>
                    <a href="{{ route('purchase_orders.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-file-list-3-line me-1"></i> Ir a Órdenes de Compra
                    </a>
                </div>
            </div>

            {{-- Barra de búsqueda unificada --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('payments.index') }}" class="d-flex gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}"
                               class="form-control"
                               placeholder="Buscar por folio o referencia…"
                               autocomplete="off">
                        @if ($search)
                            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary">
                            Buscar
                        </button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @if ($payments->isEmpty())
                    <div class="p-4 text-center">
                        <i class="ri-file-list-3-line fs-1 text-muted"></i>
                        <p class="text-muted fs-14 mt-2 mb-0">No hay pagos pendientes de autorización.</p>  
                    </div>
                @else
                    @include('payments.utilities.table', ['payments' => $payments])
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
