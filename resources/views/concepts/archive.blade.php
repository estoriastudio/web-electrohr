@extends('layouts.app')

@section('page_title', 'Conceptos — Archivados')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('concepts.index') }}">Conceptos</a></li>
    <li class="breadcrumb-item active">Archivados</li>
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
                        <i class="ri-archive-line me-2 text-muted"></i>Conceptos archivados
                    </h4>
                    <small class="text-muted">Estos conceptos no están disponibles en inventario, SOLMAT, SOLCOM ni OC.</small>
                </div>
                <a href="{{ route('concepts.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Volver al catálogo activo
                </a>
            </div>

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('concepts.archived') }}" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por código o descripción…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search)
                            <a href="{{ route('concepts.archived') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
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
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Unidad</th>
                                <th>Categoría</th>
                                <th>Tipo</th>
                                <th>Archivado el</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($concepts as $concept)
                                <tr>
                                    <td><span class="fw-semibold">{{ $concept->code }}</span></td>
                                    <td class="text-wrap" style="max-width:400px">{{ $concept->description }}</td>
                                    <td>{{ $concept->unit }}</td>
                                    <td>
                                        @if ($concept->category)
                                            <span class="fw-medium">{{ $concept->category->name }}</span>
                                            @if ($concept->subcategory)
                                                <br><span class="text-muted fs-12">{{ $concept->subcategory->name }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted fs-12">Sin categoría</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($concept->type === 'materiales')
                                            <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">Materiales</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Mantenimiento</span>
                                        @endif
                                    </td>
                                    <td>{{ $concept->archived_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @can('concepts.delete')
                                        <form action="{{ route('concepts.unarchive', $concept) }}" method="POST"
                                              class="form-unarchive-concept" data-code="{{ $concept->code }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-soft-success btn-sm" title="Restaurar">
                                                <i class="ri-inbox-unarchive-line me-1"></i> Restaurar
                                            </button>
                                        </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-archive-line fs-24 d-block mb-1 opacity-50"></i>
                                        No hay conceptos archivados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($concepts->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $concepts->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.querySelectorAll('.form-unarchive-concept').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!window.confirm('¿Restaurar el concepto «' + form.dataset.code + '»?')) {
            event.preventDefault();
        }
    });
});
</script>
@endpush
