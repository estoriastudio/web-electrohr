@extends('errors.layout')

@section('code', '429')
@section('title', 'Demasiadas solicitudes')
@section('icon', 'speed-up-line')
@section('color', 'warning')
@section('message', 'Se hicieron demasiadas solicitudes en poco tiempo. Espera un momento y vuelve a intentarlo.')

@section('actions')
    <button type="button" class="btn btn-light" onclick="goBack()">
        <i class="ri-arrow-left-line align-middle me-1"></i> Regresar
    </button>
    <button type="button" class="btn btn-primary" onclick="window.location.reload()">
        <i class="ri-refresh-line align-middle me-1"></i> Reintentar
    </button>
@endsection
