@extends('layouts.app')

@section('page_title', 'Editar asistencia')

@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('human_resources.worker-attendances.index') }}">Asistencias</a></li><li class="breadcrumb-item active">Editar</li>@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="card"><div class="card-header"><h4 class="card-title mb-0">Registro de asistencia</h4></div><div class="card-body"><form method="POST" action="{{ route('human_resources.worker-attendances.update', $workerAttendance) }}">@csrf @method('PUT') @include('human_resources.attendances._form')<div class="mt-4 d-flex gap-2"><a class="btn btn-light" href="{{ route('human_resources.worker-attendances.show', $workerAttendance) }}">Cancelar</a><button class="btn btn-primary" type="submit">Guardar cambios</button></div></form></div></div>
@endsection