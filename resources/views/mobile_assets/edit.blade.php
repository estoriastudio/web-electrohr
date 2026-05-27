@extends('layouts.app')

@push('styles')
@endpush

@section('page_title', 'Editar bien móvil')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('mobile_assets.index') }}">Bienes Móviles</a></li>
    <li class="breadcrumb-item"><a href="{{ route('mobile_assets.show', $mobileAsset) }}">{{ $mobileAsset->name }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Por favor corrige los siguientes errores:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form action="{{ route('mobile_assets.update', $mobileAsset) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row g-3">

        {{-- ══════════════════════════════════════════════════════
             CARD principal — datos del bien
        ════════════════════════════════════════════════════════ --}}
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="ri-tools-line me-1 text-muted"></i> Información del bien móvil
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="folio" class="form-label fw-medium">Folio</label>
                            <input type="text"
                                   class="form-control @error('folio') is-invalid @enderror"
                                   id="folio" name="folio"
                                   value="{{ old('folio', $mobileAsset->folio) }}"
                                   placeholder="Ej. BM-0001">
                            @error('folio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="name" class="form-label fw-medium">
                                Nombre de maquinaria <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name"
                                   value="{{ old('name', $mobileAsset->name) }}"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="brand" class="form-label fw-medium">Marca</label>
                            <input type="text"
                                   class="form-control @error('brand') is-invalid @enderror"
                                   id="brand" name="brand"
                                   value="{{ old('brand', $mobileAsset->brand) }}">
                            @error('brand')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="asset_function" class="form-label fw-medium">Función</label>
                            <input type="text"
                                   class="form-control @error('asset_function') is-invalid @enderror"
                                   id="asset_function" name="asset_function"
                                   value="{{ old('asset_function', $mobileAsset->asset_function) }}"
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
                                <option value="movil"        {{ old('type', $mobileAsset->type) === 'movil'        ? 'selected' : '' }}>Móvil</option>
                                <option value="maquinaria"   {{ old('type', $mobileAsset->type) === 'maquinaria'   ? 'selected' : '' }}>Maquinaria</option>
                                <option value="equipo_menor" {{ old('type', $mobileAsset->type) === 'equipo_menor' ? 'selected' : '' }}>Equipo menor</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Placas — solo visible cuando tipo = movil --}}
                        <div class="col-md-6" id="platesField"
                             style="{{ old('type', $mobileAsset->type) === 'movil' ? '' : 'display:none;' }}">
                            <label for="plates" class="form-label fw-medium">Placas</label>
                            <input type="text"
                                   class="form-control @error('plates') is-invalid @enderror"
                                   id="plates" name="plates"
                                   value="{{ old('plates', $mobileAsset->plates) }}"
                                   placeholder="Ej. ABC-123-4"
                                   style="text-transform: uppercase;">
                            @error('plates')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             CARD lateral — estatus y acciones
        ════════════════════════════════════════════════════════ --}}
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="ri-toggle-line me-1 text-muted"></i> Estatus
                    </h5>
                </div>
                <div class="card-body">
                    <select class="form-select @error('status') is-invalid @enderror"
                            id="status" name="status">
                        <option value="active"   {{ old('status', $mobileAsset->status) === 'active'   ? 'selected' : '' }}>Activo</option>
                        <option value="inactive" {{ old('status', $mobileAsset->status) === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="card">
                <div class="card-body d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ri-save-line me-1"></i> Guardar cambios
                    </button>
                    <a href="{{ route('mobile_assets.show', $mobileAsset) }}"
                       class="btn btn-light w-100">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>

    </div>
</form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect  = document.getElementById('type');
    const platesField = document.getElementById('platesField');

    function togglePlates() {
        if (typeSelect.value === 'movil') {
            platesField.style.display = '';
        } else {
            platesField.style.display = 'none';
            document.getElementById('plates').value = '';
        }
    }

    typeSelect.addEventListener('change', togglePlates);
});
</script>
@endpush
