@extends('layouts.app')

@section('page_title', 'Baja de trabajador')

@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('human_resources.worker-terminations.index') }}">Bajas</a></li><li class="breadcrumb-item active">{{ $workerTermination->worker->first_name }} {{ $workerTermination->worker->last_name }}</li>@endsection

@section('content')
<div class="card"><div class="card-header"><h4 class="card-title mb-0">Baja de {{ $workerTermination->worker->first_name }} {{ $workerTermination->worker->last_name }}</h4></div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Fecha</dt><dd class="col-sm-9">{{ $workerTermination->termination_date->format('d/m/Y') }}</dd><dt class="col-sm-3">Tipo</dt><dd class="col-sm-9">{{ ['resignation' => 'Renuncia', 'dismissal' => 'Despido', 'rest' => 'Descanso'][$workerTermination->termination_type] }}</dd><dt class="col-sm-3">Obra</dt><dd class="col-sm-9">{{ $workerTermination->projectWork?->name ?: '—' }}</dd><dt class="col-sm-3">Puesto / sueldo</dt><dd class="col-sm-9">{{ $workerTermination->positionCategory?->name ?: '—' }} / ${{ number_format((float) $workerTermination->salary, 2) }}</dd><dt class="col-sm-3">Motivo</dt><dd class="col-sm-9">{{ $workerTermination->reason ?: '—' }}</dd><dt class="col-sm-3">Notas</dt><dd class="col-sm-9">{{ $workerTermination->notes ?: '—' }}</dd></dl></div></div>
@endsection