@extends('layouts.app')

@section('page_title', 'Categorías de Herramientas')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Categorías de Herramientas</li>
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

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h4 class="card-title mb-0">Categorías de Herramientas</h4>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateToolCategory">
                    <i class="ri-add-line me-1"></i> Nueva Categoría
                </button>
            </div>

            <div class="card-body">
                @forelse ($rootCategories as $rootCategory)
                    <div class="card border mb-3">
                        <div class="card-header d-flex align-items-center justify-content-between bg-light-subtle py-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ri-folder-3-line text-primary fs-18"></i>
                                <span class="fw-semibold fs-15">{{ $rootCategory->name }}</span>
                                <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                    {{ $rootCategory->tools_count }} herramientas
                                </span>
                                @if ($rootCategory->status === 'inactive')
                                    <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Inactiva</span>
                                @endif
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button"
                                        class="btn btn-soft-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditToolCategory{{ $rootCategory->id }}"
                                                                                title="Editar">
                                    <i class="ri-edit-line"></i>
                                </button>
                                <form action="{{ route('tool_categories.destroy', $rootCategory) }}"
                                      method="POST"
                                                                            onsubmit="return confirm('¿Eliminar esta categoría?')">
                                    @csrf
                                    @method('DELETE')
                                                                        <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if ($rootCategory->description)
                            <div class="px-3 py-2 border-bottom bg-white text-muted fs-13">
                                {{ $rootCategory->description }}
                            </div>
                        @endif

                        @if ($rootCategory->children->count())
                            <div class="card-body p-0">
                                <table class="table align-middle table-hover table-centered mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th class="ps-3">Subcategoría</th>
                                            <th>Descripción</th>
                                            <th>Estatus</th>
                                            <th class="text-end pe-3">Herramientas</th>
                                            <th class="text-end pe-3">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rootCategory->children as $childCategory)
                                            <tr>
                                                <td class="ps-3 fw-medium">{{ $childCategory->name }}</td>
                                                <td class="text-muted fs-13">{{ $childCategory->description ?? '—' }}</td>
                                                <td>
                                                    @if ($childCategory->status === 'active')
                                                        <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activa</span>
                                                    @else
                                                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Inactiva</span>
                                                    @endif
                                                </td>
                                                <td class="text-end pe-3">{{ $childCategory->tools_count }}</td>
                                                <td class="text-end pe-3">
                                                    <div class="d-flex gap-2 justify-content-end">
                                                        <button type="button"
                                                                class="btn btn-soft-primary btn-sm"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalEditToolCategory{{ $childCategory->id }}"
                                                                                                                                title="Editar">
                                                            <i class="ri-edit-line"></i>
                                                        </button>
                                                        <form action="{{ route('tool_categories.destroy', $childCategory) }}"
                                                              method="POST"
                                                                                                                            onsubmit="return confirm('¿Eliminar esta subcategoría?')">
                                                            @csrf
                                                            @method('DELETE')
                                                                                                                        <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="card-body text-center text-muted py-3 fs-13">
                                <i class="ri-folder-open-line fs-20 d-block mb-1 opacity-50"></i>
                                Aún no hay subcategorías.
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="ri-folder-3-line fs-36 d-block mb-2 opacity-50"></i>
                        <p class="mb-0">No se encontraron categorías de herramientas.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCreateToolCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('tool_categories.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-folder-add-line me-1"></i> Nueva Categoría de Herramienta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Categoría Padre</label>
                            <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror" id="create_parent_id">
                                <option value="">Ninguna (categoría raíz)</option>
                                @foreach ($allCategories->whereNull('parent_id') as $parentOption)
                                    <option value="{{ $parentOption->id }}" @selected(old('parent_id') == $parentOption->id)>
                                        {{ $parentOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}"
                                   required maxlength="100">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text"
                                   name="description"
                                   class="form-control @error('description') is-invalid @enderror"
                                   value="{{ old('description') }}"
                                   maxlength="255">
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Estatus <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Activa</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactiva</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach ($allCategories as $category)
    <div class="modal fade" id="modalEditToolCategory{{ $category->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('tool_categories.update', $category) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-edit-line me-1"></i> Editar Categoría de Herramienta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Categoría Padre</label>
                                <select name="parent_id" class="form-select">
                                    <option value="">Ninguna (categoría raíz)</option>
                                    @foreach ($allCategories->whereNull('parent_id') as $parentOption)
                                        @continue($parentOption->id === $category->id)
                                        <option value="{{ $parentOption->id }}" @selected($category->parent_id == $parentOption->id)>
                                            {{ $parentOption->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ $category->name }}" required maxlength="100">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <input type="text" name="description" class="form-control" value="{{ $category->description }}" maxlength="255">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Estatus <span class="text-danger">*</span></label>
                                <select name="status" class="form-select" required>
                                    <option value="active" @selected($category->status === 'active')>Activa</option>
                                    <option value="inactive" @selected($category->status === 'inactive')>Inactiva</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@endsection
