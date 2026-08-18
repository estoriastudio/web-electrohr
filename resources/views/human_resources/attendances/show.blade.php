@extends('layouts.app')

@section('page_title', 'Asistencia')

@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('human_resources.worker-attendances.index') }}">Asistencias</a></li><li class="breadcrumb-item active">{{ $workerAttendance->date->format('d/m/Y') }}</li>@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="card"><div class="card-header d-flex justify-content-between"><h4 class="card-title mb-0">Asistencia del {{ $workerAttendance->date->format('d/m/Y') }}</h4><a class="btn btn-primary btn-sm" href="{{ route('human_resources.worker-attendances.edit', $workerAttendance) }}">Editar</a></div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Trabajador</dt><dd class="col-sm-9"><a href="{{ route('human_resources.workers.show', $workerAttendance->worker) }}">{{ $workerAttendance->worker->first_name }} {{ $workerAttendance->worker->last_name }}</a></dd><dt class="col-sm-3">Cuadrilla</dt><dd class="col-sm-9">{{ $workerAttendance->workerGroup->name }} - {{ $workerAttendance->workerGroup->projectWork?->name }}</dd><dt class="col-sm-3">Resultado</dt><dd class="col-sm-9">{{ $workerAttendance->attended ? 'Asistió' : 'Inasistencia' }}</dd><dt class="col-sm-3">Horas extra</dt><dd class="col-sm-9">{{ number_format((float) $workerAttendance->overtime_hours, 2) }}</dd><dt class="col-sm-3">Notas</dt><dd class="col-sm-9">{{ $workerAttendance->notes ?: '—' }}</dd></dl></div><div class="card-footer"><form method="POST" action="{{ route('human_resources.worker-attendances.destroy', $workerAttendance) }}" onsubmit="return confirm('¿Eliminar este registro?')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit">Eliminar registro</button></form></div></div>
@endsection