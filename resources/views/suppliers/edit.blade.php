@extends('layouts.app')

@push('styles')
@endpush

@section('page_title', 'Editar proveedor')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Proveedores</a></li>
    <li class="breadcrumb-item"><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->rfc_name ?? $supplier->commercial_name ?? 'Detalle' }}</a></li>
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

<form action="{{ route('suppliers.update', $supplier) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row g-3">

        {{-- ══════════════════════════════════════════════════════
             CARD 1 — Información general
        ════════════════════════════════════════════════════════ --}}
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="ri-building-line me-1 text-muted"></i> Información general
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="rfc_name" class="form-label fw-medium">
                                Razón social <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control @error('rfc_name') is-invalid @enderror"
                                   id="rfc_name" name="rfc_name"
                                   value="{{ old('rfc_name', $supplier->rfc_name) }}"
                                   required>
                            @error('rfc_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="commercial_name" class="form-label fw-medium">Nombre comercial</label>
                            <input type="text"
                                   class="form-control @error('commercial_name') is-invalid @enderror"
                                   id="commercial_name" name="commercial_name"
                                   value="{{ old('commercial_name', $supplier->commercial_name) }}">
                            @error('commercial_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="rfc_num" class="form-label fw-medium">RFC</label>
                            <input type="text"
                                   class="form-control @error('rfc_num') is-invalid @enderror"
                                   id="rfc_num" name="rfc_num"
                                   value="{{ old('rfc_num', $supplier->rfc_num) }}"
                                   maxlength="13"
                                   style="text-transform: uppercase;">
                            @error('rfc_num')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label fw-medium">Estatus</label>
                            <select class="form-select @error('status') is-invalid @enderror"
                                    id="status" name="status">
                                <option value="">— Sin definir —</option>
                                <option value="active"      {{ old('status', $supplier->status) === 'active'      ? 'selected' : '' }}>Activo</option>
                                <option value="inactive"    {{ old('status', $supplier->status) === 'inactive'    ? 'selected' : '' }}>Inactivo</option>
                                <option value="blacklisted" {{ old('status', $supplier->status) === 'blacklisted' ? 'selected' : '' }}>Bloqueado</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="attended_by" class="form-label fw-medium">Atendido por</label>
                            <input type="text"
                                   class="form-control @error('attended_by') is-invalid @enderror"
                                   id="attended_by" name="attended_by"
                                   value="{{ old('attended_by', $supplier->attended_by) }}">
                            @error('attended_by')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             CARD 2 — Datos bancarios + acciones
        ════════════════════════════════════════════════════════ --}}
        <div class="col-xl-4">
            {{-- Botones de acción --}}
            <div class="d-flex flex-column gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ri-save-line me-1"></i> Guardar cambios
                </button>
                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-light w-100">
                    <i class="ri-arrow-left-line me-1"></i> Cancelar
                </a>
            </div>
        </div>

    </div>
</form>

@endsection

@push('scripts')
<script>
    // Convertir RFC y SWIFT a mayúsculas automáticamente
    document.getElementById('rfc_num')?.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });
    document.getElementById('swift_code')?.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });
</script>
@endpush
