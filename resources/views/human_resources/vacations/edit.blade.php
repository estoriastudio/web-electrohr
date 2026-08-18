@extends('layouts.app')

@section('page_title', 'Editar vacaciones')

@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('human_resources.worker-vacations.index') }}">Vacaciones</a></li><li class="breadcrumb-item active">Editar</li>@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="card"><div class="card-header"><h4 class="card-title mb-0">Solicitud de vacaciones</h4></div><div class="card-body"><form method="POST" action="{{ route('human_resources.worker-vacations.update', $workerVacation) }}">@csrf @method('PUT') @include('human_resources.vacations._form')<div class="mt-4 d-flex gap-2"><a class="btn btn-light" href="{{ route('human_resources.worker-vacations.show', $workerVacation) }}">Cancelar</a><button class="btn btn-primary" type="submit">Guardar cambios</button></div></form></div></div>
@endsection