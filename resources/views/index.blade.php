@extends('layouts.app')

@push('styles')
@endpush

@section('page_title', 'Bienvenida')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">Inicio</a></li>
    <li class="breadcrumb-item active">Bienvenida</li>
@endsection

@section('content')
    <h1>Bienvenido a ERP ElectroHR</h1>
@endsection

@push('scripts')
@endpush