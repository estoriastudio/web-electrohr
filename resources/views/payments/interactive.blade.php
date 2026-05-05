@extends('layouts.app')

@section('page_title', 'Autorización de Pagos')

@push('styles')
    <style>body, html { overflow-x: hidden; }</style>
@endpush

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
                    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-file-list-3-line me-1"></i> Regresar a Listado
                    </a>
                    <a href="{{ route('purchase_orders.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-file-list-3-line me-1"></i> Ir a Órdenes de Compra
                    </a>
                </div>
            </div>

            <div class="card-body p-3 p-sm-4 p-md-5 d-flex justify-content-center align-items-center">
                @include('payments.utilities.swipable_cards', ['payments' => $payments])
            </div>
        </div>
    </div>
</div>
@endsection
