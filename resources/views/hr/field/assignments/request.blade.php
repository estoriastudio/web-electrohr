@extends('layouts.app')

@section('page_title', 'Asignación de personal')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Recursos Humanos</li>
    <li class="breadcrumb-item active">Solicitar personal</li>
@endsection

@section('content')
    @include('partials.flash')
    @include('partials.livewire-modal')

    <livewire:hr.personnel-request-builder />
@endsection
