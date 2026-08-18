@extends('layouts.app')

@section('page_title', 'Editar cuadrilla')

@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('human_resources.worker-groups.index') }}">Cuadrillas</a></li><li class="breadcrumb-item"><a href="{{ route('human_resources.worker-groups.show', $workerGroup) }}">{{ $workerGroup->name }}</a></li><li class="breadcrumb-item active">Editar</li>@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="card"><div class="card-header"><h4 class="card-title mb-0">Información de la cuadrilla</h4></div><div class="card-body"><form method="POST" action="{{ route('human_resources.worker-groups.update', $workerGroup) }}">@csrf @method('PUT') @include('human_resources.worker-groups._form')<div class="mt-4 d-flex gap-2"><a href="{{ route('human_resources.worker-groups.show', $workerGroup) }}" class="btn btn-light">Cancelar</a><button class="btn btn-primary" type="submit">Guardar cambios</button></div></form></div></div>
@endsection