@extends('layouts.app')

@section('page_title', 'Bitácora')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Recursos Humanos</li>
    <li class="breadcrumb-item active">Bitácora</li>
@endsection

@section('content')
    @include('partials.flash')

    <livewire:hr.audit-log-table />
@endsection
