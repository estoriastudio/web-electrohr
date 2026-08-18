@extends('layouts.app')

@section('page_title', 'Editar trabajador')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('human_resources.workers.index') }}">Trabajadores</a></li>
    <li class="breadcrumb-item"><a href="{{ route('human_resources.workers.show', $worker) }}">{{ $worker->first_name }} {{ $worker->last_name }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="card"><div class="card-header"><h4 class="card-title mb-0">Información del trabajador</h4></div><div class="card-body"><form method="POST" action="{{ route('human_resources.workers.update', $worker) }}">@csrf @method('PUT') @include('human_resources.workers._form')<div class="mt-4 d-flex gap-2"><a href="{{ route('human_resources.workers.show', $worker) }}" class="btn btn-light">Cancelar</a><button class="btn btn-primary" type="submit">Guardar cambios</button></div></form></div></div>
@endsection