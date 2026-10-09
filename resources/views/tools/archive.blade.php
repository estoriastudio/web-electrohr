@extends('layouts.app')

@section('page_title', 'Herramientas — Archivadas')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tools.index') }}">Herramientas</a></li>
    <li class="breadcrumb-item active">Archivadas</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">
                        <i class="ri-archive-line me-2 text-muted"></i>Herramientas archivadas
                    </h4>
                    <small class="text-muted">Estas herramientas no están disponibles en Inventario ni en Control de uso.</small>
                </div>
                <a href="{{ route('tools.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Volver al registro activo
                </a>
            </div>

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('tools.archived') }}" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por número económico, descripción, serie, marca..."
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search)
                            <a href="{{ route('tools.archived') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>No. Económico</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th>Marca / Modelo</th>
                                <th>No. Serie</th>
                                <th>Archivada el</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tools as $tool)
                                @php
                                    $selectedCategory = $tool->category;
                                    $rootCategoryForTool = $selectedCategory?->parent_id ? $selectedCategory->parent : $selectedCategory;
                                    $subcategoryForTool = $selectedCategory?->parent_id ? $selectedCategory : null;
                                @endphp
                                <tr>
                                    <td><span class="fw-semibold">{{ $tool->economic_number }}</span></td>
                                    <td class="text-wrap" style="max-width:320px;">{{ $tool->description }}</td>
                                    <td>
                                        @if ($rootCategoryForTool)
                                            <span class="fw-medium">{{ $rootCategoryForTool->name }}</span>
                                            @if ($subcategoryForTool)
                                                <br><span class="text-muted fs-12">{{ $subcategoryForTool->name }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">Sin categoría</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $tool->brand ?: 'Sin marca' }}</span>
                                        <br>
                                        <span class="fs-12 text-muted">{{ $tool->model ?: 'Sin modelo' }}</span>
                                    </td>
                                    <td>{{ $tool->serial_number ?: 'Sin serie' }}</td>
                                    <td>{{ $tool->archived_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('tools.show', $tool) }}" class="btn btn-soft-info btn-sm" title="Ver detalle">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @can('tools.delete')
                                            <form action="{{ route('tools.unarchive', $tool) }}" method="POST"
                                                  class="form-unarchive-tool" data-code="{{ $tool->economic_number }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-soft-success btn-sm" title="Restaurar">
                                                    <i class="ri-inbox-unarchive-line me-1"></i> Restaurar
                                                </button>
                                            </form>
                                            @endcan
                                            @role('admin')
                                                <button type="button"
                                                        class="btn btn-soft-danger btn-sm btn-force-delete-tool"
                                                        title="Eliminar permanentemente"
                                                        data-action="{{ route('tools.force_destroy', $tool) }}"
                                                        data-code="{{ $tool->economic_number }}"
                                                        data-description="{{ $tool->description }}">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            @endrole
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-archive-line fs-24 d-block mb-1 opacity-50"></i>
                                        No hay herramientas archivadas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($tools->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $tools->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@role('admin')
<div class="modal fade" id="modalForceDeleteTool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formForceDeleteTool" action="" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="ri-delete-bin-line me-1"></i> Eliminar permanentemente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Vas a eliminar <strong id="force_delete_tool_code"></strong>
                        <span class="text-muted">— <span id="force_delete_tool_description"></span></span>
                    </p>
                    <div class="alert alert-danger mb-3">
                        <div class="fw-semibold mb-1"><i class="ri-error-warning-line me-1"></i> Esta acción no se puede deshacer</div>
                        <ul class="mb-0 ps-3">
                            <li>Se eliminarán sus controles de uso, calibraciones y fotografías.</li>
                            <li>Las entradas de inventario que la referencian perderán el vínculo con la herramienta.</li>
                            <li>No se puede eliminar si tiene salidas de inventario vinculadas; en ese caso mantenla archivada.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="ri-delete-bin-line me-1"></i> Eliminar permanentemente</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endrole

@endsection

@push('scripts')
<script>
document.querySelectorAll('.form-unarchive-tool').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!window.confirm('¿Restaurar la herramienta «' + form.dataset.code + '»?')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('.btn-force-delete-tool').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('formForceDeleteTool').action = this.dataset.action;
        document.getElementById('force_delete_tool_code').textContent = this.dataset.code;
        document.getElementById('force_delete_tool_description').textContent = this.dataset.description;
        new bootstrap.Modal(document.getElementById('modalForceDeleteTool')).show();
    });
});
</script>
@endpush
