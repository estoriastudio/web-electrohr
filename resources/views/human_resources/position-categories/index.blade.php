@extends('layouts.app')

@section('page_title', 'Categorías de puesto')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Categorías de puesto</li>
@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <div>
            <h4 class="card-title mb-0">Categorías de puesto</h4>
            <span class="text-muted fs-12">Catálogo utilizado en trabajadores, bajas y nómina.</span>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPositionCategoryModal">
            <i class="ri-add-line me-1"></i>Nueva categoría
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light-subtle"><tr><th>Nombre</th><th>Estatus</th><th>Trabajadores</th><th>Bajas</th><th>Notas</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                @forelse($positionCategories as $positionCategory)
                    <tr>
                        <td class="fw-medium">{{ $positionCategory->name }}</td>
                        <td><span class="badge {{ $positionCategory->active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $positionCategory->active ? 'Activa' : 'Inactiva' }}</span></td>
                        <td>{{ $positionCategory->workers_count }}</td>
                        <td>{{ $positionCategory->terminations_count }}</td>
                        <td class="text-muted">{{ $positionCategory->notes ?: '—' }}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#editPositionCategoryModal{{ $positionCategory->id }}" title="Editar"><i class="ri-edit-line"></i></button>
                            <form method="POST" action="{{ route('human_resources.position-categories.destroy', $positionCategory) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta categoría?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light btn-sm text-danger" title="Eliminar" @disabled($positionCategory->workers_count || $positionCategory->terminations_count)><i class="ri-delete-bin-line"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay categorías de puesto registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($positionCategories->hasPages())<div class="card-footer d-flex justify-content-end">{{ $positionCategories->links('pagination::bootstrap-5') }}</div>@endif
</div>

<div class="modal fade" id="createPositionCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="{{ route('human_resources.position-categories.store') }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Nueva categoría de puesto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">@include('human_resources.position-categories._form', ['positionCategory' => null])</div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
        </form>
    </div></div>
</div>

@foreach($positionCategories as $positionCategory)
    <div class="modal fade" id="editPositionCategoryModal{{ $positionCategory->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form method="POST" action="{{ route('human_resources.position-categories.update', $positionCategory) }}">
                @csrf
                @method('PUT')
                <div class="modal-header"><h5 class="modal-title">Editar categoría de puesto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">@include('human_resources.position-categories._form', ['positionCategory' => $positionCategory])</div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
            </form>
        </div></div>
    </div>
@endforeach
@endsection