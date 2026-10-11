@extends('layouts.app')

@section('page_title', 'Mis proyectos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Mis proyectos</li>
@endsection

@section('content')

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h4 class="card-title mb-0">Mis proyectos</h4>
                <span class="text-muted fs-13">{{ $projects->total() }} registros</span>
            </div>

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('my-projects.index') }}" class="d-flex gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                        <input type="text" name="search" value="{{ $search }}"
                               class="form-control" placeholder="Buscar por nombre o cliente…"
                               autocomplete="off">
                        @if ($search)
                            <a href="{{ route('my-projects.index') }}" class="btn btn-outline-secondary" title="Limpiar">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Proyecto</th>
                                <th>Cliente</th>
                                <th># Obras</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($projects as $project)
                                @php
                                    $sMap = [
                                        'active'   => ['label' => 'Activo',   'class' => 'bg-success-subtle text-success'],
                                        'inactive' => ['label' => 'Inactivo', 'class' => 'bg-warning-subtle text-warning'],
                                    ];
                                    $s = $sMap[$project->status] ?? ['label' => $project->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                                <span class="text-primary fw-semibold">{{ strtoupper(substr($project->name, 0, 1)) }}</span>
                                            </div>
                                            <div>
                                                <a href="{{ route('my-projects.show', $project) }}" class="text-dark fw-medium fs-15">{{ $project->name }}</a>
                                                @if ($project->city || $project->state)
                                                    <div class="text-muted fs-12">{{ implode(', ', array_filter([$project->city, $project->state])) }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $project->client_name }}</td>
                                    <td><span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $project->works_count }}</span></td>
                                    <td><span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span></td>
                                    <td>
                                        <a href="{{ route('my-projects.show', $project) }}" class="btn btn-light btn-sm" title="Ver detalle">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                                        No tienes proyectos asignados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($projects->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $projects->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
