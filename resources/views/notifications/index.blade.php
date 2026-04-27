@extends('layouts.app')

@section('page_title', 'Auditoría')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Auditoría</li>
@endsection

@section('content')

<div class="row">
    <div class="col-xl-12">

        {{-- Filtros --}}
        @include('notifications.utilities._search_options')

        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Registro de actividad</h4>
                <span class="text-muted fs-13">{{ $notifications->total() }} registros</span>
            </div>
            <div class="card-body p-0">
                @include('notifications.utilities._table')
            </div>
            <div class="card-footer border-top">
                {{ $notifications->links('pagination::bootstrap-5') }}
            </div>
        </div>

    </div>
</div>

@endsection
