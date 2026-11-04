@extends('layouts.app')

@section('page_title', 'Cuadrillas')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Recursos Humanos</li>
    <li class="breadcrumb-item active">Cuadrillas</li>
@endsection

@section('content')
    @include('partials.flash')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
            <div>
                <h4 class="card-title mb-0">Catálogo de cuadrillas</h4>
                <span class="text-muted fs-12">La cuadrilla es una etiqueta del trabajador; no determina su Obra.</span>
            </div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#workerGroupModal-new">
                <i class="ri-add-line me-1"></i> Nueva cuadrilla
            </button>
        </div>

        <div class="card-body border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" name="search" value="{{ $search }}" placeholder="Buscar cuadrilla">
                </div>
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="status">
                        <option value="">Todos los estados</option>
                        <option value="active" @selected($status === 'active')>Activas</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactivas</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-sm btn-outline-primary"><i class="ri-search-line"></i></button>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                    <thead class="bg-light-subtle">
                        <tr>
                            <th>Cuadrilla</th>
                            <th>Trabajadores</th>
                            <th>Estado</th>
                            <th>Notas</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workerGroups as $workerGroup)
                            <tr>
                                <td>
                                    <a href="{{ route('human_resources.workers.index', ['group' => $workerGroup->id, 'section' => 'active']) }}" class="text-dark fw-medium">{{ $workerGroup->name }}</a>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $workerGroup->workers_count }}</span></td>
                                <td>
                                    @if ($workerGroup->status === 'active')
                                        <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activa</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Inactiva</span>
                                    @endif
                                </td>
                                <td class="text-muted text-wrap" style="max-width: 320px;">{{ $workerGroup->notes ?: '—' }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-soft-primary btn-sm" data-bs-toggle="modal" data-bs-target="#workerGroupModal-{{ $workerGroup->id }}">
                                            <i class="ri-edit-line align-middle fs-18"></i>
                                        </button>
                                        <form method="POST" action="{{ route('human_resources.worker-groups.destroy', $workerGroup) }}" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar esta cuadrilla?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-soft-danger btn-sm" @disabled($workerGroup->workers_count > 0)>
                                                <i class="ri-delete-bin-line align-middle fs-18"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay cuadrillas registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($workerGroups->hasPages())
            <div class="card-footer border-top">
                {{ $workerGroups->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <div class="modal fade" id="workerGroupModal-new" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('human_resources.worker-groups.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Nueva cuadrilla</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @include('human_resources.worker-groups._form', ['workerGroup' => null])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($workerGroups as $workerGroup)
        <div class="modal fade" id="workerGroupModal-{{ $workerGroup->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('human_resources.worker-groups.update', $workerGroup) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Editar cuadrilla</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            @include('human_resources.worker-groups._form', ['workerGroup' => $workerGroup])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@if ($errors->any() && old('form_context'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('workerGroupModal-{{ old('form_context') }}');
                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endpush
@endif
@endsection
