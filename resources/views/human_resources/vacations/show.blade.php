@extends('layouts.app')

@section('page_title', 'Vacaciones')

@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('human_resources.worker-vacations.index') }}">Vacaciones</a></li><li class="breadcrumb-item active">Solicitud</li>@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="card">
	<div class="card-header d-flex flex-wrap justify-content-between gap-2">
		<h4 class="card-title mb-0">Vacaciones de {{ $workerVacation->worker->first_name }} {{ $workerVacation->worker->last_name }}</h4>
		<div class="d-flex gap-2">
			<a class="btn btn-primary btn-sm" href="{{ route('human_resources.worker-vacations.edit', $workerVacation) }}">Editar</a>

			@if ($workerVacation->status === 'in_progress')
				<form method="POST" action="{{ route('human_resources.worker-vacations.approve', $workerVacation) }}">
					@csrf
					<button class="btn btn-success btn-sm">Aprobar</button>
				</form>
			@endif

			@if (in_array($workerVacation->status, ['in_progress', 'approved'], true))
				<form method="POST" action="{{ route('human_resources.worker-vacations.cancel', $workerVacation) }}">
					@csrf
					<button class="btn btn-outline-danger btn-sm">Cancelar</button>
				</form>
			@endif
		</div>
	</div>
	<div class="card-body">
		<dl class="row mb-0">
			<dt class="col-sm-3">Periodo</dt>
			<dd class="col-sm-9">{{ $workerVacation->start_date->format('d/m/Y') }} al {{ $workerVacation->end_date->format('d/m/Y') }}</dd>
			<dt class="col-sm-3">Días</dt>
			<dd class="col-sm-9">{{ $workerVacation->total_days }}</dd>
			<dt class="col-sm-3">Fecha de solicitud</dt>
			<dd class="col-sm-9">{{ $workerVacation->request_date->format('d/m/Y') }}</dd>
			<dt class="col-sm-3">Notas</dt>
			<dd class="col-sm-9">{{ $workerVacation->notes ?: '—' }}</dd>
		</dl>
	</div>
	<div class="card-footer">
		<form method="POST" action="{{ route('human_resources.worker-vacations.destroy', $workerVacation) }}" onsubmit="return confirm('¿Eliminar esta solicitud?')">
			@csrf
			@method('DELETE')
			<button class="btn btn-outline-danger btn-sm">Eliminar solicitud</button>
		</form>
	</div>
</div>
@endsection