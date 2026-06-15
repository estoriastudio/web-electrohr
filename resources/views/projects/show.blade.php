@extends('layouts.app')

@section('page_title', $project->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyectos</a></li>
    <li class="breadcrumb-item active">{{ $project->name }}</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     BARRA DE INFORMACIÓN DEL PROYECTO
══════════════════════════════════════════════════════════════════ --}}
@php
    $sMap = [
        'active'   => ['label' => 'Activo',   'class' => 'bg-success-subtle text-success'],
        'inactive' => ['label' => 'Inactivo', 'class' => 'bg-warning-subtle text-warning'],
    ];
    $ps = $sMap[$project->status] ?? ['label' => $project->status, 'class' => 'bg-secondary-subtle text-secondary'];
@endphp

<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-md bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="ri-folder-3-line fs-22 text-primary"></i>
                </div>
                <div>
                    <h5 class="fw-semibold mb-0">{{ $project->name }}</h5>
                    <div class="text-muted fs-13">
                        <i class="ri-user-line me-1"></i>{{ $project->client_name }}
                        @if ($project->city || $project->state)
                            &nbsp;·&nbsp;<i class="ri-map-pin-line me-1"></i>
                            {{ implode(', ', array_filter([$project->city, $project->state])) }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-center">
                    <div class="fw-semibold fs-18 text-primary">{{ $project->works_count }}</div>
                    <div class="text-muted fs-12">Obras</div>
                </div>
                <div class="text-center">
                    <div class="fw-semibold fs-18 text-primary">$ {{ number_format((float) ($project->project_value ?? 0), 2) }}</div>
                    <div class="text-muted fs-12">Valor</div>
                </div>
                <span class="badge {{ $ps['class'] }} py-1 px-3 fs-12">{{ $ps['label'] }}</span>
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modalCreateWork">
                    <i class="ri-add-line me-1"></i> Nueva Obra
                </button>
            </div>
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     OBRAS DEL PROYECTO
══════════════════════════════════════════════════════════════════ --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold text-muted mb-0">
        <i class="ri-building-2-line me-1"></i> Obras asociadas
    </h6>
</div>

@if ($project->works->isEmpty())
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="ri-building-2-line fs-36 d-block mb-2 opacity-50"></i>
            <p class="mb-0">Este proyecto aún no tiene obras registradas.</p>
            <button type="button" class="btn btn-sm btn-primary mt-3"
                    data-bs-toggle="modal" data-bs-target="#modalCreateWork">
                <i class="ri-add-line me-1"></i> Nueva Obra
            </button>
        </div>
    </div>
@else
    <div class="row g-3">
        @foreach ($project->works as $work)
            @php
                $wsMap = [
                    'active'   => ['label' => 'Activa',   'class' => 'bg-success-subtle text-success'],
                    'inactive' => ['label' => 'Inactiva', 'class' => 'bg-warning-subtle text-warning'],
                ];
                $ws = $wsMap[$work->status] ?? ['label' => $work->status, 'class' => 'bg-secondary-subtle text-secondary'];
            @endphp
            <div class="col-xl-4 col-md-6">
                <div class="card h-100 border">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-building-2-line fs-18 text-info"></i>
                            </div>
                            <span class="badge {{ $ws['class'] }} py-1 px-2 fs-11">{{ $ws['label'] }}</span>
                        </div>

                        <h6 class="fw-semibold mb-1">
                            <a href="{{ route('project_works.show', $work) }}" class="text-dark text-decoration-none">
                                {{ $work->name }}
                            </a>
                        </h6>
                        <p class="text-muted fs-13 mb-2">
                            <i class="ri-user-line me-1"></i>{{ $project->client_name }}
                        </p>

                        {{-- Datos del contrato --}}
                        <div class="mb-3">
                            @if ($work->supervisor)
                                <div class="text-muted fs-12 mb-1">
                                    <i class="ri-user-star-line me-1"></i>
                                    <span class="fw-medium">Supervisor:</span> {{ $work->supervisor }}
                                </div>
                            @endif
                            @if ($work->contract_number)
                                <div class="text-muted fs-12 mb-1">
                                    <i class="ri-file-text-line me-1"></i>
                                    <span class="fw-medium">Contrato:</span> {{ $work->contract_number }}
                                </div>
                            @endif
                            @if ($work->contract_value)
                                <div class="text-muted fs-12 mb-1">
                                    <i class="ri-money-dollar-circle-line me-1"></i>
                                    <span class="fw-medium">Valor:</span>
                                    {{ $work->currency }} {{ number_format((float) str_replace(',', '', $work->contract_value), 2) }}
                                </div>
                            @endif
                            @if ($work->contract_start_date || $work->contract_end_date)
                                <div class="text-muted fs-12">
                                    <i class="ri-calendar-line me-1"></i>
                                    {{ $work->contract_start_date ? \Carbon\Carbon::parse($work->contract_start_date)->format('d/m/Y') : '—' }}
                                    &rarr;
                                    {{ $work->contract_end_date ? \Carbon\Carbon::parse($work->contract_end_date)->format('d/m/Y') : '—' }}
                                </div>
                            @endif
                        </div>

                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                    <i class="ri-file-list-3-line me-1"></i>
                                    {{ $work->purchase_orders_count }} {{ Str::plural('orden', $work->purchase_orders_count) }}
                                </span>
                            </div>
                            <div class="d-flex gap-1">
                                <a href="{{ route('project_works.show', $work) }}"
                                   class="btn btn-light btn-sm" title="Ver detalle">
                                    <i class="ri-eye-line"></i>
                                </a>
                                <form action="{{ route('project_works.destroy', $work) }}"
                                      method="POST"
                                      onsubmit="return confirm('¿Eliminar la obra «{{ addslashes($work->name) }}»?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Nueva Obra
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalCreateWork" tabindex="-1" aria-labelledby="modalCreateWorkLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('project_works.store') }}" method="POST">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateWorkLabel">
                        <i class="ri-building-2-line me-1"></i> Nueva Obra
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        Proyecto: <strong>{{ $project->name }}</strong>
                    </p>

                    {{-- Nombre --}}
                    <div class="mb-3">
                        <label for="work_name" class="form-label fw-medium">Nombre de la Obra <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="work_name" name="name"
                               value="{{ old('name') }}"
                               placeholder="Ej. Bodega 3, Planta Norte…"
                               autofocus required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Supervisor / Residente --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="work_supervisor" class="form-label fw-medium">Supervisor</label>
                            <input type="text"
                                   class="form-control @error('supervisor') is-invalid @enderror"
                                   id="work_supervisor" name="supervisor"
                                   value="{{ old('supervisor') }}"
                                   placeholder="Nombre del supervisor">
                            @error('supervisor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="work_resident" class="form-label fw-medium">Residente</label>
                            <input type="text"
                                   class="form-control @error('resident') is-invalid @enderror"
                                   id="work_resident" name="resident"
                                   value="{{ old('resident') }}"
                                   placeholder="Nombre del residente">
                            @error('resident')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Número de contrato --}}
                    <div class="mb-3">
                        <label for="work_contract_number" class="form-label fw-medium">Número de Contrato</label>
                        <input type="text"
                               class="form-control @error('contract_number') is-invalid @enderror"
                               id="work_contract_number" name="contract_number"
                               value="{{ old('contract_number') }}"
                               placeholder="Ej. CONT-2026-001">
                        @error('contract_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Fechas de contrato --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="work_contract_start_date" class="form-label fw-medium">Fecha Inicio</label>
                            <input type="date"
                                   class="form-control @error('contract_start_date') is-invalid @enderror"
                                   id="work_contract_start_date" name="contract_start_date"
                                   value="{{ old('contract_start_date') }}">
                            @error('contract_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="work_contract_end_date" class="form-label fw-medium">Fecha Fin</label>
                            <input type="date"
                                   class="form-control @error('contract_end_date') is-invalid @enderror"
                                   id="work_contract_end_date" name="contract_end_date"
                                   value="{{ old('contract_end_date') }}">
                            @error('contract_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Valor de contrato / Moneda --}}
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="work_contract_value" class="form-label fw-medium">Valor del Contrato</label>
                            <input type="text"
                                   class="form-control @error('contract_value') is-invalid @enderror"
                                   id="work_contract_value" name="contract_value"
                                   value="{{ old('contract_value') }}"
                                   placeholder="Ej. 1,500,000.00">
                            @error('contract_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="work_currency" class="form-label fw-medium">Moneda</label>
                            <select class="form-select @error('currency') is-invalid @enderror"
                                    id="work_currency" name="currency">
                                <option value="">— Seleccionar —</option>
                                <option value="MXN" {{ old('currency') === 'MXN' ? 'selected' : '' }}>MXN</option>
                                <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD</option>
                                <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>EUR</option>
                            </select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Crear obra
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
@endpush
