@extends('layouts.app')

@section('page_title', 'Editar Obra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyectos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.show', $projectWork->project) }}">{{ $projectWork->project->name }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('project_works.show', $projectWork) }}">{{ $projectWork->name }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form action="{{ route('project_works.update', $projectWork) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="card mb-3">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">
                <i class="ri-building-2-line me-1 text-muted"></i> Información general
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">

                {{-- Nombre --}}
                <div class="col-md-8">
                    <label for="name" class="form-label fw-medium">Nombre de la Obra <span class="text-danger">*</span></label>
                    <input type="text"
                           class="form-control @error('name') is-invalid @enderror"
                           id="name" name="name"
                           value="{{ old('name', $projectWork->name) }}"
                           required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Estatus --}}
                <div class="col-md-4">
                    <label for="status" class="form-label fw-medium">Estatus <span class="text-danger">*</span></label>
                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                        <option value="active"   {{ old('status', $projectWork->status) === 'active'   ? 'selected' : '' }}>Activa</option>
                        <option value="inactive" {{ old('status', $projectWork->status) === 'inactive' ? 'selected' : '' }}>Inactiva</option>
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">
                <i class="ri-file-text-line me-1 text-muted"></i> Datos del Contrato
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">

                {{-- Supervisor / Residente --}}
                <div class="col-md-6">
                    <label for="supervisor" class="form-label fw-medium">Supervisor</label>
                    <input type="text"
                           class="form-control @error('supervisor') is-invalid @enderror"
                           id="supervisor" name="supervisor"
                           value="{{ old('supervisor', $projectWork->supervisor) }}"
                           placeholder="Nombre del supervisor">
                    @error('supervisor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="resident" class="form-label fw-medium">Residente</label>
                    <input type="text"
                           class="form-control @error('resident') is-invalid @enderror"
                           id="resident" name="resident"
                           value="{{ old('resident', $projectWork->resident) }}"
                           placeholder="Nombre del residente">
                    @error('resident')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Número de contrato --}}
                <div class="col-md-6">
                    <label for="contract_number" class="form-label fw-medium">Número de Contrato</label>
                    <input type="text"
                           class="form-control @error('contract_number') is-invalid @enderror"
                           id="contract_number" name="contract_number"
                           value="{{ old('contract_number', $projectWork->contract_number) }}"
                           placeholder="Ej. CONT-2026-001">
                    @error('contract_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Fechas --}}
                <div class="col-md-3">
                    <label for="contract_start_date" class="form-label fw-medium">Fecha Inicio</label>
                    <input type="date"
                           class="form-control @error('contract_start_date') is-invalid @enderror"
                           id="contract_start_date" name="contract_start_date"
                           value="{{ old('contract_start_date', $projectWork->contract_start_date) }}">
                    @error('contract_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="contract_end_date" class="form-label fw-medium">Fecha Fin</label>
                    <input type="date"
                           class="form-control @error('contract_end_date') is-invalid @enderror"
                           id="contract_end_date" name="contract_end_date"
                           value="{{ old('contract_end_date', $projectWork->contract_end_date) }}">
                    @error('contract_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Valor / Moneda --}}
                <div class="col-md-6">
                    <label for="contract_value" class="form-label fw-medium">Valor del Contrato</label>
                    <input type="text"
                           class="form-control @error('contract_value') is-invalid @enderror"
                           id="contract_value" name="contract_value"
                           value="{{ old('contract_value', $projectWork->contract_value) }}"
                           placeholder="Ej. 1,500,000.00">
                    @error('contract_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="currency" class="form-label fw-medium">Moneda</label>
                    <select class="form-select @error('currency') is-invalid @enderror" id="currency" name="currency">
                        <option value="">— Seleccionar —</option>
                        <option value="MXN" {{ old('currency', $projectWork->currency) === 'MXN' ? 'selected' : '' }}>MXN</option>
                        <option value="USD" {{ old('currency', $projectWork->currency) === 'USD' ? 'selected' : '' }}>USD</option>
                        <option value="EUR" {{ old('currency', $projectWork->currency) === 'EUR' ? 'selected' : '' }}>EUR</option>
                    </select>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a href="{{ route('project_works.show', $projectWork) }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> Guardar cambios
        </button>
    </div>

</form>

@endsection

@push('scripts')
@endpush
