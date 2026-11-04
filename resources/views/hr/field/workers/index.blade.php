@extends('layouts.app')

@section('page_title', 'Mis trabajadores')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Recursos Humanos</li>
    <li class="breadcrumb-item active">Mis trabajadores</li>
@endsection

@section('content')
    @include('partials.flash')

    <livewire:hr.my-workers-table />
@endsection
