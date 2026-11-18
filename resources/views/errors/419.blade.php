@extends('errors.layout')

@section('code', '419')
@section('title', 'Sesión expirada')
@section('icon', 'time-line')
@section('color', 'warning')
@section('message', 'Tu sesión o el formulario expiró por inactividad. Recarga la página e inténtalo de nuevo.')

@section('actions')
    <button type="button" class="btn btn-light" onclick="goBack()">
        <i class="ri-arrow-left-line align-middle me-1"></i> Regresar
    </button>
    <a href="{{ url('/') }}" class="btn btn-primary">
        <i class="ri-login-box-line align-middle me-1"></i> Ir al inicio
    </a>
@endsection
