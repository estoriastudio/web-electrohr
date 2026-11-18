@extends('errors.layout')

@section('code', '500')
@section('title', 'Error del servidor')
@section('icon', 'error-warning-line')
@section('color', 'danger')
@section('message', 'Algo salió mal de nuestro lado. Puedes reintentar o regresar a la pantalla anterior.')

@section('actions')
    <button type="button" class="btn btn-light" onclick="goBack()">
        <i class="ri-arrow-left-line align-middle me-1"></i> Regresar
    </button>
    <button type="button" class="btn btn-soft-primary" onclick="window.location.reload()">
        <i class="ri-refresh-line align-middle me-1"></i> Reintentar
    </button>
    <a href="{{ url('/') }}" class="btn btn-primary">
        <i class="ri-home-4-line align-middle me-1"></i> Ir al inicio
    </a>
@endsection
