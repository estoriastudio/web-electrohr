@extends('layouts.app')

@section('page_title', 'Categorías de Conceptos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Categorías de Conceptos</li>
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
                <h4 class="card-title mb-0">Categorías de Conceptos</h4>
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modalCreateCategory">
                    <i class="ri-add-line me-1"></i> Nueva Categoría
                </button>
            </div>

            {{-- Tabs de tipo --}}
            <div class="card-body border-bottom py-0 px-0">
                <ul class="nav nav-tabs nav-tabs-custom px-3" id="typeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $type === 'materiales' ? 'active' : '' }}"
                           href="{{ route('concept_categories.index', ['type' => 'materiales']) }}">
                            <i class="ri-box-3-line me-1"></i> Materiales
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $type === 'mantenimiento' ? 'active' : '' }}"
                           href="{{ route('concept_categories.index', ['type' => 'mantenimiento']) }}">
                            <i class="ri-tools-line me-1"></i> Mantenimiento
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                @forelse ($categories as $category)
                    <div class="card border mb-3">
                        <div class="card-header d-flex align-items-center justify-content-between bg-light-subtle py-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ri-folder-3-line text-primary fs-18"></i>
                                <span class="fw-semibold fs-15">{{ $category->name }}</span>
                                @if($category->description)
                                    <span class="text-muted fs-13">— {{ $category->description }}</span>
                                @endif
                                <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                    {{ $category->concepts_count }} {{ Str::plural('concepto', $category->concepts_count) }}
                                </span>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light btn-sm"
                                        title="Asignar compradores"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalSyncUsers{{ $category->id }}">
                                    <i class="ri-user-add-line"></i>
                                    @if($category->users->count())
                                        <span class="badge bg-success-subtle text-success ms-1">{{ $category->users->count() }}</span>
                                    @endif
                                </button>
                                <button type="button" class="btn btn-soft-primary btn-sm"
                                        title="Editar categoría"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditCategory{{ $category->id }}">
                                    <i class="ri-edit-line"></i>
                                </button>
                                <button type="button" class="btn btn-soft-secondary btn-sm"
                                        title="Nueva subcategoría"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalCreateSubcategory{{ $category->id }}">
                                    <i class="ri-add-line"></i>
                                </button>
                                <form action="{{ route('concept_categories.destroy', $category) }}" method="POST"
                                      onsubmit="return confirm('¿Eliminar la categoría «{{ addslashes($category->name) }}»? Se quitará la categoría de todos sus conceptos.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Compradores asignados --}}
                        @if($category->users->count())
                            <div class="px-3 py-2 border-bottom bg-white d-flex align-items-center gap-2 flex-wrap">
                                <span class="text-muted fs-12 me-1"><i class="ri-user-line"></i> Compradores:</span>
                                @foreach($category->users as $u)
                                    <span class="badge bg-info-subtle text-info py-1 px-2 fs-12">{{ $u->name }}</span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Subcategorías --}}
                        @if($category->subcategories->count())
                            <div class="card-body p-0">
                                <table class="table align-middle table-hover table-centered mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th class="ps-3">Subcategoría</th>
                                            <th>Descripción</th>
                                            <th class="text-end pe-3">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($category->subcategories as $sub)
                                            <tr>
                                                <td class="ps-3 fw-medium">{{ $sub->name }}</td>
                                                <td class="text-muted fs-13">{{ $sub->description ?? '—' }}</td>
                                                <td class="text-end pe-3">
                                                    <div class="d-flex gap-2 justify-content-end">
                                                        <button type="button" class="btn btn-soft-primary btn-sm"
                                                                title="Editar"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalEditSub{{ $sub->id }}">
                                                            <i class="ri-edit-line"></i>
                                                        </button>
                                                        <form action="{{ route('concept_categories.subcategories.destroy', [$category, $sub]) }}"
                                                              method="POST"
                                                              onsubmit="return confirm('¿Eliminar la subcategoría «{{ addslashes($sub->name) }}»?')">
                                                            @csrf @method('DELETE')
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
                                Sin subcategorías. Usa el botón <i class="ri-add-line"></i> para agregar.
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="ri-folder-3-line fs-36 d-block mb-2 opacity-50"></i>
                        <p class="mb-0">No hay categorías de tipo <strong>{{ $type === 'materiales' ? 'Materiales' : 'Mantenimiento' }}</strong>.</p>
                        <button type="button" class="btn btn-primary mt-3"
                                data-bs-toggle="modal" data-bs-target="#modalCreateCategory">
                            <i class="ri-add-line me-1"></i> Crear primera categoría
                        </button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ══ MODAL Crear Categoría ══ --}}
<div class="modal fade" id="modalCreateCategory" tabindex="-1" aria-labelledby="modalCreateCategoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('concept_categories.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateCategoryLabel">
                        <i class="ri-folder-add-line me-1"></i> Nueva Categoría
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required maxlength="100" autocomplete="off">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="materiales" {{ $type === 'materiales' ? 'selected' : '' }}>Materiales</option>
                                <option value="mantenimiento" {{ $type === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                            </select>
                            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="description" class="form-control"
                                   value="{{ old('description') }}" maxlength="255">
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

{{-- ══ MODALES por Categoría ══ --}}
@foreach($categories as $category)

    {{-- Modal Editar Categoría --}}
    <div class="modal fade" id="modalEditCategory{{ $category->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('concept_categories.update', $category) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-edit-line me-1"></i> Editar Categoría</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                       value="{{ $category->name }}" required maxlength="100">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select name="type" class="form-select" required>
                                    <option value="materiales" {{ $category->type === 'materiales' ? 'selected' : '' }}>Materiales</option>
                                    <option value="mantenimiento" {{ $category->type === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <input type="text" name="description" class="form-control"
                                       value="{{ $category->description }}" maxlength="255">
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

    {{-- Modal Nueva Subcategoría --}}
    <div class="modal fade" id="modalCreateSubcategory{{ $category->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('concept_categories.subcategories.store', $category) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="ri-folder-add-line me-1"></i>
                            Nueva Subcategoría en <span class="text-primary">{{ $category->name }}</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                       required maxlength="100" autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <input type="text" name="description" class="form-control" maxlength="255">
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

    {{-- Modal Asignar Compradores --}}
    <div class="modal fade" id="modalSyncUsers{{ $category->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('concept_categories.sync_users', $category) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="ri-user-add-line me-1"></i>
                            Compradores — <span class="text-primary">{{ $category->name }}</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted fs-13 mb-3">
                            Selecciona los compradores asignados a esta categoría. Se precargarán automáticamente en las SOLCOM con conceptos de esta categoría.
                        </p>
                        <div class="row g-2">
                            @foreach($purchasingUsers as $pu)
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="user_ids[]"
                                               value="{{ $pu->id }}"
                                               id="user{{ $category->id }}_{{ $pu->id }}"
                                               {{ $category->users->contains($pu->id) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="user{{ $category->id }}_{{ $pu->id }}">
                                            {{ $pu->name }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                            @if($purchasingUsers->isEmpty())
                                <div class="col-12 text-muted fs-13">No hay usuarios con rol de compras.</div>
                            @endif
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

    {{-- Modales Editar Subcategorías --}}
    @foreach($category->subcategories as $sub)
        <div class="modal fade" id="modalEditSub{{ $sub->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('concept_categories.subcategories.update', [$category, $sub]) }}" method="POST">
                        @csrf @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="ri-edit-line me-1"></i> Editar Subcategoría</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control"
                                           value="{{ $sub->name }}" required maxlength="100">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Descripción</label>
                                    <input type="text" name="description" class="form-control"
                                           value="{{ $sub->description }}" maxlength="255">
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

@endforeach

@endsection
