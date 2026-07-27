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
                            <label for="folio" class="form-label fw-medium">Folio / Número Económico</label>
                            <input type="text"
                                   class="form-control @error('folio') is-invalid @enderror"
                                   id="folio" name="folio"
                                   value="{{ old('folio', $mobileAsset->folio) }}"
                                   placeholder="Ej. MOV-001">
                            @error('folio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="policy" class="form-label fw-medium">Póliza</label>
                            <input type="text"
                                   class="form-control @error('policy') is-invalid @enderror"
                                   id="policy" name="policy"
                                   value="{{ old('policy', $mobileAsset->policy) }}"
                                   placeholder="Ej. POL-12345">
                            @error('policy')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="card_number" class="form-label fw-medium">No. Tarjeta</label>
                            <input type="text"
                                   class="form-control @error('card_number') is-invalid @enderror"
                                   id="card_number" name="card_number"
                                   value="{{ old('card_number', $mobileAsset->card_number) }}"
                                   placeholder="Ej. 12345678">
                            @error('card_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="milage" class="form-label fw-medium">Kilometraje</label>
                            <input type="text"
                                   class="form-control @error('milage') is-invalid @enderror"
                                   id="milage" name="milage"
                                   value="{{ old('milage', is_numeric($mobileAsset->milage) ? number_format($mobileAsset->milage) : $mobileAsset->milage) }}"
                                   placeholder="Ej. 120000">
                            @error('milage')
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
                            <label for="model" class="form-label fw-medium">Modelo</label>
                            <input type="text"
                                   class="form-control @error('model') is-invalid @enderror"
                                   id="model" name="model"
                                   value="{{ old('model', $mobileAsset->model) }}">
                            @error('model')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="year" class="form-label fw-medium">Año</label>
                            <input type="text"
                                   class="form-control @error('year') is-invalid @enderror"
                                   id="year" name="year"
                                   value="{{ old('year', $mobileAsset->year) }}"
                                   placeholder="2024">
                            @error('year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="color" class="form-label fw-medium">Color</label>
                            <input type="text"
                                   class="form-control @error('color') is-invalid @enderror"
                                   id="color" name="color"
                                   value="{{ old('color', $mobileAsset->color) }}">
                            @error('color')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="serial" class="form-label fw-medium">Serie / NIV</label>
                            <input type="text"
                                   class="form-control @error('serial') is-invalid @enderror"
                                   id="serial" name="serial"
                                   value="{{ old('serial', $mobileAsset->serial) }}">
                            @error('serial')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="operator" class="form-label fw-medium">Operador</label>
                            <input type="text"
                                   class="form-control @error('operator') is-invalid @enderror"
                                   id="operator" name="operator"
                                   value="{{ old('operator', $mobileAsset->operator) }}">
                            @error('operator')
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
                            <label for="type" class="form-label fw-medium">Tipo de bien</label>
                            <select class="form-select @error('type') is-invalid @enderror"
                                    id="type" name="type">
                                <option value="">— Seleccionar —</option>
                                <option value="parque_vehicular"  {{ old('type', $mobileAsset->type) === 'parque_vehicular'  ? 'selected' : '' }}>Parque Vehicular</option>
                                <option value="maquinaria_pesada" {{ old('type', $mobileAsset->type) === 'maquinaria_pesada' ? 'selected' : '' }}>Maquinaria Pesada</option>
                                <option value="semiremolque"      {{ old('type', $mobileAsset->type) === 'semiremolque'      ? 'selected' : '' }}>SemiRemolque</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Placas — solo para parque vehicular --}}
                        <div class="col-md-6" id="platesField"
                             style="{{ old('type', $mobileAsset->type) === 'parque_vehicular' ? '' : 'display:none;' }}">
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
                        <option value="activo"     {{ old('status', $mobileAsset->status) === 'activo'     ? 'selected' : '' }}>Activo</option>
                        <option value="vendido"    {{ old('status', $mobileAsset->status) === 'vendido'    ? 'selected' : '' }}>Vendido</option>
                        <option value="obsoleto"   {{ old('status', $mobileAsset->status) === 'obsoleto'   ? 'selected' : '' }}>Obsoleto</option>
                        <option value="reparacion" {{ old('status', $mobileAsset->status) === 'reparacion' ? 'selected' : '' }}>En reparación</option>
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
        if (typeSelect.value === 'parque_vehicular') {
            platesField.style.display = '';
        } else {
            platesField.style.display = 'none';
            document.getElementById('plates').value = '';
        }
    }

    typeSelect.addEventListener('change', togglePlates);

    // ── Máscara de kilometraje ────────────────────────────────────────
    (function () {
        var milageInput = document.getElementById('milage');
        if (!milageInput) return;

        function formatMilage(el) {
            var raw = el.value.replace(/[^0-9]/g, '');
            if (raw === '') { el.value = ''; return; }
            var num = parseInt(raw, 10);
            var formatted = num.toLocaleString('en-US');
            var pos = el.selectionStart + (formatted.length - el.value.length);
            el.value = formatted;
            try { el.setSelectionRange(pos, pos); } catch (e) {}
        }

        milageInput.addEventListener('input', function () { formatMilage(this); });

        // Limpiar comas antes de enviar
        milageInput.closest('form').addEventListener('submit', function () {
            milageInput.value = milageInput.value.replace(/[^0-9]/g, '');
        });
    }());
});
</script>
@endpush
