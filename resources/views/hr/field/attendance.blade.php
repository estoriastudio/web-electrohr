@extends('layouts.app')

@section('page_title', 'Asistencias')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Recursos Humanos</li>
    <li class="breadcrumb-item active">Asistencias</li>
@endsection

@section('content')
    @include('partials.flash')
    @include('partials.livewire-modal')

    <livewire:hr.attendance-table mode="field" />
@endsection
