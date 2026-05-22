@extends('layouts.app')

@push('styles')
@endpush

@section('page_title', 'Bienes Móviles')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Bienes Móviles</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
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
                <div>
                    <h4 class="card-title mb-0">Listado de Bienes Móviles</h4>
                </div>
                @role('admin|orders')
                <div class="d-flex gap-2">
                    {{-- Exportar --}}
                    <a href="{{ route('mobile_assets.export') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-download-2-line me-1"></i> Exportar
                    </a>

                    {{-- Crear nuevo bien --}}
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalCreateAsset">
                        <i class="ri-add-line me-1"></i> Crear nuevo bien
                    </button>
                </div>
                @endrole
            </div>

            {{-- Barra de búsqueda --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('mobile_assets.index') }}" class="d-flex gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}"
                               class="form-control"
                               placeholder="Buscar por nombre, folio o marca…"
                               autocomplete="off">
                        @if ($search)
                            <a href="{{ route('mobile_assets.index') }}" class="btn btn-outline-secondary" title="Limpiar búsqueda">
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
                                <th>Nombre de maquinaria</th>
                                <th>Folio</th>
                                <th>Marca</th>
                                <th>Tipo</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $typeMap = [
                                    'movil'        => ['label' => 'Móvil',         'class' => 'bg-info-subtle text-info'],
                                    'maquinaria'   => ['label' => 'Maquinaria',    'class' => 'bg-primary-subtle text-primary'],
                                    'equipo_menor' => ['label' => 'Equipo menor',  'class' => 'bg-secondary-subtle text-secondary'],
                                ];
                            @endphp
                            @forelse ($mobileAssets as $asset)
                                @php
                                    $t = $typeMap[$asset->type] ?? ['label' => '—', 'class' => 'bg-secondary-subtle text-secondary'];
                                @endphp
                                <tr>
                                    {{-- Nombre --}}
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                                <span class="text-primary fw-semibold">
                                                    {{ strtoupper(substr($asset->name, 0, 1)) }}
                                                </span>
                                            </div>
                                            <a href="{{ route('mobile_assets.show', $asset) }}"
                                               class="text-dark fw-medium fs-15">
                                                {{ $asset->name }}
                                            </a>
                                        </div>
                                    </td>

                                    {{-- Folio --}}
                                    <td class="text-muted fs-13">{{ $asset->folio ?? '—' }}</td>

                                    {{-- Marca --}}
                                    <td class="fs-13">{{ $asset->brand ?? '—' }}</td>

                                    {{-- Tipo --}}
                                    <td>
                                        <span class="badge {{ $t['class'] }} py-1 px-2 fs-12">{{ $t['label'] }}</span>
                                    </td>

                                    {{-- Estatus --}}
                                    <td>
                                        @if ($asset->status === 'active')
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activo</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Inactivo</span>
                                        @endif
                                    </td>

                                    {{-- Acciones --}}
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('mobile_assets.show', $asset) }}"
                                               class="btn btn-light btn-sm" title="Ver detalle">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @role('admin|orders')
                                            <a href="{{ route('mobile_assets.edit', $asset) }}"
                                               class="btn btn-soft-primary btn-sm" title="Editar">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <form action="{{ route('mobile_assets.destroy', $asset) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('¿Eliminar este bien móvil?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                            @endrole
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-tools-line fs-24 d-block mb-1 opacity-50"></i>
                                        No hay bienes móviles registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($mobileAssets->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $mobileAssets->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Crear nuevo bien móvil
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalCreateAsset" tabindex="-1" aria-labelledby="modalCreateAssetLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('mobile_assets.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateAssetLabel">
                        <i class="ri-tools-line me-1"></i> Nuevo bien móvil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="folio" class="form-label fw-medium">Folio</label>
                            <input type="text" class="form-control @error('folio') is-invalid @enderror"
                                   id="folio" name="folio"
                                   value="{{ old('folio') }}"
                                   placeholder="Ej. BM-0001">
                            @error('folio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="name" class="form-label fw-medium">
                                Nombre de maquinaria <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name"
                                   value="{{ old('name') }}"
                                   placeholder="Ej. Retroexcavadora Cat 320"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="brand" class="form-label fw-medium">Marca</label>
                            <input type="text" class="form-control @error('brand') is-invalid @enderror"
                                   id="brand" name="brand"
                                   value="{{ old('brand') }}"
                                   placeholder="Ej. Caterpillar">
                            @error('brand')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="asset_function" class="form-label fw-medium">Función</label>
                            <input type="text" class="form-control @error('asset_function') is-invalid @enderror"
                                   id="asset_function" name="asset_function"
                                   value="{{ old('asset_function') }}"
                                   placeholder="Ej. Excavación y movimiento de tierra">
                            @error('asset_function')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="type" class="form-label fw-medium">Tipo de maquinaria</label>
                            <select class="form-select @error('type') is-invalid @enderror"
                                    id="type" name="type">
                                <option value="">— Seleccionar —</option>
                                <option value="movil"        {{ old('type') === 'movil'        ? 'selected' : '' }}>Móvil</option>
                                <option value="maquinaria"   {{ old('type') === 'maquinaria'   ? 'selected' : '' }}>Maquinaria</option>
                                <option value="equipo_menor" {{ old('type') === 'equipo_menor' ? 'selected' : '' }}>Equipo menor</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Placas — solo visible cuando tipo = movil --}}
                        <div class="col-md-6" id="platesField" style="{{ old('type') === 'movil' ? '' : 'display:none;' }}">
                            <label for="plates" class="form-label fw-medium">Placas</label>
                            <input type="text" class="form-control @error('plates') is-invalid @enderror"
                                   id="plates" name="plates"
                                   value="{{ old('plates') }}"
                                   placeholder="Ej. ABC-123-4"
                                   style="text-transform: uppercase;">
                            @error('plates')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Crear bien
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect   = document.getElementById('type');
    const platesField  = document.getElementById('platesField');

    function togglePlates() {
        if (typeSelect.value === 'movil') {
            platesField.style.display = '';
        } else {
            platesField.style.display = 'none';
            document.getElementById('plates').value = '';
        }
    }

    typeSelect.addEventListener('change', togglePlates);

    @if ($errors->any())
    // Re-abrir modal si hubo errores de validación
    var modal = new bootstrap.Modal(document.getElementById('modalCreateAsset'));
    modal.show();
    @endif
});
</script>
@endpush
