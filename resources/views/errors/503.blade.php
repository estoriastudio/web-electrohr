@extends('errors.layout')

@section('code', '503')
@section('title', 'En Mantenimiento')
@section('icon', 'tools-line')
@section('color', 'info')
@section('message', 'El sistema está en mantenimiento. Vuelve a intentarlo en unos minutos.')

@section('actions')
    <button type="button" class="btn btn-primary" onclick="window.location.reload()">
        <i class="ri-refresh-line align-middle me-1"></i> Reintentar
    </button>
@endsection
