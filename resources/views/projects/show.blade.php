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

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-semibold mb-1">No se pudo guardar la obra. Revisa los campos marcados.</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
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
                    <div class="fw-semibold fs-18 text-primary">{{ $project->currency }} {{ number_format((float) ($project->project_value ?? 0), 2) }}</div>
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

@include('projects._estimates')

{{-- ══════════════════════════════════════════════════════════════
     CHECKLIST DOCUMENTAL DEL PROYECTO
══════════════════════════════════════════════════════════════════ --}}
<div class="card mb-3">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="ri-file-shield-2-line me-1 text-muted"></i> Documentación del Proyecto
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-centered">
                <thead class="bg-light-subtle">
                    <tr>
                        <th style="width: 18px;"></th>
                        <th>Documento</th>
                        <th>Fecha subida</th>
                        <th>Archivo</th>
                        @role('admin|Proyectos')
                        <th>Acción</th>
                        @endrole
                    </tr>
                </thead>
                <tbody>
                    @foreach ($docCategories as $catKey => $category)
                        @php
                            $docsUploaded = $project->documents->whereIn('document_type', array_keys($category['docs']))->whereNotNull('file_path')->count();
                            $docsTotal    = count($category['docs']);
                        @endphp
                        {{-- Fila de encabezado de categoría (colapsable) --}}
                        <tr class="table-light"
                            style="cursor: pointer;"
                            data-bs-toggle="collapse"
                            data-bs-target=".doc-group-{{ $catKey }}"
                            aria-expanded="false">
                            @role('admin|Proyectos')
                            <td colspan="5" class="fw-semibold text-uppercase fs-12 text-muted py-2 ps-3">
                            @else
                            <td colspan="4" class="fw-semibold text-uppercase fs-12 text-muted py-2 ps-3">
                            @endrole
                                <i class="ri-folder-2-line me-1"></i>{{ $category['label'] }}
                                <span class="badge bg-{{ $docsUploaded === $docsTotal ? 'success' : 'secondary' }}-subtle text-{{ $docsUploaded === $docsTotal ? 'success' : 'secondary' }} ms-2 fw-normal">
                                    {{ $docsUploaded }}/{{ $docsTotal }}
                                </span>
                                <i class="ri-arrow-down-s-line float-end me-2 category-chevron"></i>
                            </td>
                        </tr>
                        {{-- Filas de documentos dentro de la categoría --}}
                        @foreach ($category['docs'] as $dt => $dtLabel)
                            @php
                                $docRecord = $project->documents->firstWhere('document_type', $dt);
                                $hasFile   = $docRecord && $docRecord->file_path;
                            @endphp
                            <tr class="collapse doc-group-{{ $catKey }}">
                                <td class="ps-4">
                                    <span class="rounded-circle d-inline-block"
                                          style="width: 12px; height: 12px; background: {{ $hasFile ? '#28a745' : '#adb5bd' }};"
                                          title="{{ $dtLabel }}: {{ $hasFile ? 'Subido' : 'Pendiente' }}">
                                    </span>
                                </td>
                                <td class="fw-medium fs-14">{{ $dtLabel }}</td>
                                <td class="text-muted fs-12">
                                    {{ $docRecord && $docRecord->uploaded_at ? $docRecord->uploaded_at->format('d/m/Y') : '—' }}
                                </td>
                                <td>
                                    @if ($hasFile)
                                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($docRecord->file_path) }}"
                                           target="_blank" class="btn btn-light btn-sm" title="Ver documento">
                                            <i class="ri-file-download-line"></i>
                                        </a>
                                    @else
                                        <span class="text-muted fs-12">Sin archivo</span>
                                    @endif
                                </td>
                                @role('admin|Proyectos')
                                <td>
                                    <button type="button"
                                            class="btn btn-soft-primary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalProjectDoc_{{ $dt }}">
                                        <i class="ri-upload-2-line me-1"></i>
                                        {{ $hasFile ? 'Reemplazar' : 'Subir' }}
                                    </button>
                                </td>
                                @endrole
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-top">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 bg-light-subtle">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="fw-semibold text-uppercase fs-12 text-muted mb-0">
                        <i class="ri-file-edit-line me-1"></i>Convenios
                    </h6>
                    <span class="badge bg-primary-subtle text-primary fw-normal">{{ $project->agreements->count() }}</span>
                </div>
                @role('admin|Proyectos')
                    @if ($project->works->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-primary"
                                data-bs-toggle="modal" data-bs-target="#modalCreateProjectAgreement">
                            <i class="ri-add-line me-1"></i> Agregar nuevo convenio
                        </button>
                    @else
                        <button type="button" class="btn btn-sm btn-primary" disabled title="Registra una obra antes de crear un convenio">
                            <i class="ri-add-line me-1"></i> Agregar nuevo convenio
                        </button>
                    @endif
                @endrole
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-centered">
                    <thead>
                        <tr>
                            <th>Convenio</th>
                            <th>Obras aplicables</th>
                            <th>Importes</th>
                            <th>Fechas</th>
                            <th>Nombramientos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($project->agreements as $agreement)
                            <tr>
                                <td class="fw-semibold">{{ $agreement->agreement_number }}</td>
                                <td class="fs-12 text-muted">
                                    {{ $agreement->works->pluck('name')->implode(', ') }}
                                </td>
                                <td class="fs-12 text-muted">
                                    <div>Contratado: $ {{ number_format((float) $agreement->contracted_amount, 2) }}</div>
                                    <div>Vigente: $ {{ number_format((float) $agreement->current_amount, 2) }}</div>
                                    <div class="fw-medium text-primary">Nuevo: $ {{ number_format((float) $agreement->new_amount, 2) }}</div>
                                </td>
                                <td class="fs-12 text-muted">
                                    <div>Contratada: {{ $agreement->contracted_end_date->format('d/m/Y') }}</div>
                                    <div>Vigente: {{ $agreement->current_end_date->format('d/m/Y') }}</div>
                                    <div class="fw-medium text-primary">Nueva: {{ $agreement->new_end_date->format('d/m/Y') }}</div>
                                    <div>Plazo: {{ $agreement->contracted_term_days }} días</div>
                                </td>
                                <td>
                                    <a href="{{ route('projects.agreements.appointments.download', [$project, $agreement]) }}"
                                       target="_blank" class="btn btn-light btn-sm" title="Ver nombramientos">
                                        <i class="ri-file-download-line"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="ri-file-edit-line fs-24 d-block mb-1 opacity-50"></i>
                                    No hay convenios registrados para este proyecto.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Modales de subida (uno por tipo de documento) ─────────────────── --}}
@role('admin|Proyectos')
@foreach ($docCategories as $catKey => $category)
    @foreach ($category['docs'] as $dt => $dtLabel)
        @php $docRecord = $project->documents->firstWhere('document_type', $dt); @endphp
        <div class="modal fade" id="modalProjectDoc_{{ $dt }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('projects.document.upload', [$project, $dt]) }}"
                          class="js-multipart-upload-form"
                          data-chunk-init-url="{{ route('projects.document.chunk.init', [$project, $dt]) }}"
                          data-chunk-upload-url="{{ route('projects.document.chunk.upload', [$project, $dt]) }}"
                          data-chunk-finalize-url="{{ route('projects.document.chunk.finalize', [$project, $dt]) }}"
                          data-chunk-abort-url="{{ route('projects.document.chunk.abort', [$project, $dt]) }}"
                          method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="ri-file-add-line me-1"></i>
                                <span class="text-muted fw-normal fs-13">{{ $category['label'] }} /</span>
                                {{ $dtLabel }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label fw-medium">
                                Archivo <span class="text-danger">*</span>
                                <span class="text-muted fw-normal fs-12">(PDF, JPG, PNG — máx. 100 MB, carga por partes)</span>
                            </label>
                            <input type="file" name="file" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="progress mt-3 js-upload-progress-wrap d-none" style="height: 8px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated js-upload-progress"
                                     role="progressbar"
                                     style="width: 0%;"
                                     aria-valuenow="0"
                                     aria-valuemin="0"
                                     aria-valuemax="100"></div>
                            </div>
                            <div class="form-text mt-2 js-upload-status text-muted d-none"></div>
                            @if ($docRecord && $docRecord->file_path)
                                <div class="form-text">Ya existe un archivo. Sube uno nuevo para reemplazarlo.</div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endforeach
@endrole

@role('admin|Proyectos')
@php
    $defaultAgreementAmount = number_format((float) ($project->current_agreement_value ?? $project->project_value), 2, '.', '');
    $selectedAgreementWorkIds = old('work_ids', []);
@endphp
<div class="modal fade" id="modalCreateProjectAgreement" tabindex="-1" aria-labelledby="modalCreateProjectAgreementLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form action="{{ route('projects.agreements.store', $project) }}"
                  class="js-project-agreement-form"
                  data-chunk-init-url="{{ route('projects.agreements.chunk.init', $project) }}"
                  data-chunk-upload-url="{{ route('projects.agreements.chunk.upload', $project) }}"
                  data-chunk-finalize-url="{{ route('projects.agreements.chunk.finalize', $project) }}"
                  data-chunk-abort-url="{{ route('projects.agreements.chunk.abort', $project) }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="form_context" value="agreement">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateProjectAgreementLabel">
                        <i class="ri-file-edit-line me-1"></i> Agregar nuevo convenio
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Obras aplicables <span class="text-danger">*</span></label>
                        <div class="border rounded p-2 js-agreement-works @error('work_ids') border-danger @enderror">
                            @foreach ($project->works as $work)
                                <div class="form-check">
                                    <input class="form-check-input js-agreement-work" type="checkbox"
                                           name="work_ids[]" value="{{ $work->id }}" id="agreement_work_{{ $work->id }}"
                                           data-contract-end-date="{{ $work->contract_end_date }}"
                                           {{ in_array($work->id, $selectedAgreementWorkIds) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="agreement_work_{{ $work->id }}">{{ $work->name }}</label>
                                </div>
                            @endforeach
                        </div>
                        @error('work_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="agreement_contracted_amount" class="form-label fw-medium">Importe contratado <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control @error('contracted_amount') is-invalid @enderror"
                                   id="agreement_contracted_amount" name="contracted_amount"
                                   value="{{ old('contracted_amount', $defaultAgreementAmount) }}" required>
                            @error('contracted_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="agreement_current_amount" class="form-label fw-medium">Importe vigente <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control @error('current_amount') is-invalid @enderror"
                                   id="agreement_current_amount" name="current_amount"
                                   value="{{ old('current_amount', $defaultAgreementAmount) }}" required>
                            @error('current_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="agreement_contracted_end_date" class="form-label fw-medium">Fecha fin contratada <span class="text-danger">*</span></label>
                            <input type="date" class="form-control js-agreement-reference-date @error('contracted_end_date') is-invalid @enderror"
                                   id="agreement_contracted_end_date" name="contracted_end_date"
                                   value="{{ old('contracted_end_date') }}" required>
                            @error('contracted_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="agreement_contracted_term_days" class="form-label fw-medium">Plazo contratado (días) <span class="text-danger">*</span></label>
                            <input type="number" min="0" step="1" class="form-control @error('contracted_term_days') is-invalid @enderror"
                                   id="agreement_contracted_term_days" name="contracted_term_days"
                                   value="{{ old('contracted_term_days') }}" required>
                            @error('contracted_term_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="agreement_current_end_date" class="form-label fw-medium">Fecha fin vigente <span class="text-danger">*</span></label>
                            <input type="date" class="form-control js-agreement-reference-date @error('current_end_date') is-invalid @enderror"
                                   id="agreement_current_end_date" name="current_end_date"
                                   value="{{ old('current_end_date') }}" required>
                            @error('current_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="agreement_number" class="form-label fw-medium">Número de convenio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('agreement_number') is-invalid @enderror"
                                   id="agreement_number" name="agreement_number"
                                   value="{{ old('agreement_number') }}" required>
                            @error('agreement_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="agreement_new_amount" class="form-label fw-medium">Importe nuevo <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control @error('new_amount') is-invalid @enderror"
                                   id="agreement_new_amount" name="new_amount" value="{{ old('new_amount') }}" required>
                            @error('new_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="agreement_new_end_date" class="form-label fw-medium">Fecha fin nueva <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('new_end_date') is-invalid @enderror"
                                   id="agreement_new_end_date" name="new_end_date" value="{{ old('new_end_date') }}" required>
                            @error('new_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="agreement_appointments_file" class="form-label fw-medium">
                            Nombramientos <span class="text-danger">*</span>
                            <span class="text-muted fw-normal fs-12">(PDF, JPG, PNG — máx. 100 MB, carga por partes)</span>
                        </label>
                        <input type="file" class="form-control @error('appointments_file') is-invalid @enderror"
                               id="agreement_appointments_file" name="appointments_file"
                               accept=".pdf,.jpg,.jpeg,.png" required>
                        @error('appointments_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="progress mt-3 js-upload-progress-wrap d-none" style="height: 8px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated js-upload-progress"
                                 role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="form-text mt-2 js-upload-status text-muted d-none"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Guardar convenio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endrole


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
                            @if ($work->supervisorUser || $work->supervisor)
                                <div class="text-muted fs-12 mb-1">
                                    <i class="ri-user-star-line me-1"></i>
                                    <span class="fw-medium">Supervisor:</span> {{ $work->supervisorUser?->name ?? $work->supervisor }}
                                </div>
                            @endif
                            @if ($work->residentUser || $work->resident)
                                <div class="text-muted fs-12 mb-1">
                                    <i class="ri-user-line me-1"></i>
                                    <span class="fw-medium">Residente:</span> {{ $work->residentUser?->name ?? $work->resident }}
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
                            <select class="form-select @error('supervisor_user_id') is-invalid @enderror" id="work_supervisor" name="supervisor_user_id">
                                <option value="">Sin asignar</option>
                                @foreach($engineers as $engineer)
                                    <option value="{{ $engineer->id }}" @selected((string) old('supervisor_user_id') === (string) $engineer->id)>{{ $engineer->name }}</option>
                                @endforeach
                            </select>
                            @error('supervisor_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="work_resident" class="form-label fw-medium">Residente</label>
                            <select class="form-select @error('resident_user_id') is-invalid @enderror" id="work_resident" name="resident_user_id">
                                <option value="">Sin asignar</option>
                                @foreach($engineers as $engineer)
                                    <option value="{{ $engineer->id }}" @selected((string) old('resident_user_id') === (string) $engineer->id)>{{ $engineer->name }}</option>
                                @endforeach
                            </select>
                            @error('resident_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            function setUploadStatus(form, message, isError) {
                var statusEl = form.querySelector('.js-upload-status');
                if (!statusEl) return;
                statusEl.classList.remove('d-none', 'text-muted', 'text-danger', 'text-success');
                statusEl.classList.add(isError ? 'text-danger' : 'text-muted');
                statusEl.textContent = message;
            }

            function setUploadProgress(form, percent) {
                var wrap = form.querySelector('.js-upload-progress-wrap');
                var bar = form.querySelector('.js-upload-progress');
                if (!wrap || !bar) return;
                wrap.classList.remove('d-none');
                bar.style.width = percent + '%';
                bar.setAttribute('aria-valuenow', String(percent));
            }

            async function postJson(url, payload) {
                var response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin',
                });

                var data = null;
                try {
                    data = await response.json();
                } catch (err) {
                    data = null;
                }

                if (!response.ok) {
                    var errorMessage = data?.message || 'No se pudo completar la operación.';
                    throw new Error(errorMessage);
                }

                return data || {};
            }

            async function postChunk(url, formData) {
                var response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: formData,
                    credentials: 'same-origin',
                });

                var data = null;
                try {
                    data = await response.json();
                } catch (err) {
                    data = null;
                }

                if (!response.ok) {
                    var errorMessage = data?.message || 'No se pudo subir un chunk.';
                    throw new Error(errorMessage);
                }

                return data || {};
            }

            document.querySelectorAll('.js-multipart-upload-form').forEach(function (form) {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    if (form.dataset.uploading === '1') {
                        return;
                    }

                    var fileInput = form.querySelector('input[name="file"]');
                    var submitBtn = form.querySelector('button[type="submit"]');
                    var file = fileInput?.files?.[0];

                    if (!file) {
                        setUploadStatus(form, 'Selecciona un archivo para continuar.', true);
                        return;
                    }

                    var uploadId = null;
                    form.dataset.uploading = '1';
                    submitBtn.disabled = true;

                    try {
                        setUploadProgress(form, 1);
                        setUploadStatus(form, 'Iniciando carga por lotes...', false);

                        var initData = await postJson(form.dataset.chunkInitUrl, {
                            filename: file.name,
                            size: file.size,
                        });

                        uploadId = initData.upload_id;
                        var chunkSize = initData.chunk_size || (5 * 1024 * 1024);
                        var maxParallel = initData.max_parallel || 3;
                        var totalParts = Math.ceil(file.size / chunkSize);
                        var uploadedBytes = 0;
                        var nextPartNumber = 1;
                        async function uploadWorker() {
                            while (true) {
                                var partNumber = nextPartNumber;
                                nextPartNumber += 1;

                                if (partNumber > totalParts) {
                                    return;
                                }

                                var start = (partNumber - 1) * chunkSize;
                                var end = Math.min(start + chunkSize, file.size);
                                var chunk = file.slice(start, end);

                                var chunkPayload = new FormData();
                                chunkPayload.append('upload_id', uploadId);
                                chunkPayload.append('chunk_number', String(partNumber - 1));
                                chunkPayload.append('chunk', chunk, 'chunk_' + (partNumber - 1));

                                await postChunk(form.dataset.chunkUploadUrl, chunkPayload);

                                uploadedBytes += chunk.size;
                                var progress = Math.min(99, Math.round((uploadedBytes / file.size) * 100));
                                setUploadProgress(form, progress);
                                setUploadStatus(form, 'Subiendo: ' + progress + '%', false);
                            }
                        }

                        var workers = [];
                        var workerCount = Math.min(maxParallel, totalParts);
                        for (var i = 0; i < workerCount; i++) {
                            workers.push(uploadWorker());
                        }

                        await Promise.all(workers);

                        setUploadStatus(form, 'Finalizando carga...', false);

                        await postJson(form.dataset.chunkFinalizeUrl, {
                            upload_id: uploadId,
                        });

                        setUploadProgress(form, 100);
                        setUploadStatus(form, 'Documento subido correctamente. Actualizando vista...', false);
                        window.location.reload();
                    } catch (error) {
                        if (uploadId) {
                            try {
                                await postJson(form.dataset.chunkAbortUrl, {
                                    upload_id: uploadId,
                                });
                            } catch (abortError) {
                                // Si falla el abort, no bloqueamos al usuario.
                            }
                        }

                        setUploadStatus(form, error.message || 'No se pudo subir el documento.', true);
                        submitBtn.disabled = false;
                        form.dataset.uploading = '0';
                    }
                });
            });

            document.querySelectorAll('.js-project-agreement-form').forEach(function (form) {
                var referenceDates = form.querySelectorAll('.js-agreement-reference-date');

                function updateReferenceDates() {
                    var dates = Array.from(form.querySelectorAll('.js-agreement-work:checked'))
                        .map(function (work) { return work.dataset.contractEndDate; })
                        .filter(Boolean)
                        .sort();

                    if (!dates.length) {
                        return;
                    }

                    referenceDates.forEach(function (input) {
                        input.value = dates[0];
                    });
                }

                form.querySelectorAll('.js-agreement-work').forEach(function (work) {
                    work.addEventListener('change', updateReferenceDates);
                });
                updateReferenceDates();

                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    if (form.dataset.uploading === '1') {
                        return;
                    }

                    var fileInput = form.querySelector('input[name="appointments_file"]');
                    var submitBtn = form.querySelector('button[type="submit"]');
                    var file = fileInput?.files?.[0];
                    if (!file) {
                        setUploadStatus(form, 'Selecciona el archivo de nombramientos para continuar.', true);
                        return;
                    }

                    var workIds = Array.from(form.querySelectorAll('.js-agreement-work:checked'))
                        .map(function (work) { return work.value; });
                    if (!workIds.length) {
                        setUploadStatus(form, 'Selecciona al menos una obra aplicable.', true);
                        return;
                    }

                    var uploadId = null;
                    form.dataset.uploading = '1';
                    submitBtn.disabled = true;

                    try {
                        setUploadProgress(form, 1);
                        setUploadStatus(form, 'Iniciando carga por partes...', false);

                        var initData = await postJson(form.dataset.chunkInitUrl, {
                            work_ids: workIds,
                            contracted_amount: form.querySelector('[name="contracted_amount"]').value,
                            current_amount: form.querySelector('[name="current_amount"]').value,
                            contracted_end_date: form.querySelector('[name="contracted_end_date"]').value,
                            contracted_term_days: form.querySelector('[name="contracted_term_days"]').value,
                            current_end_date: form.querySelector('[name="current_end_date"]').value,
                            agreement_number: form.querySelector('[name="agreement_number"]').value,
                            new_amount: form.querySelector('[name="new_amount"]').value,
                            new_end_date: form.querySelector('[name="new_end_date"]').value,
                            filename: file.name,
                            size: file.size,
                        });

                        uploadId = initData.upload_id;
                        var chunkSize = initData.chunk_size || (5 * 1024 * 1024);
                        var maxParallel = initData.max_parallel || 3;
                        var totalParts = Math.ceil(file.size / chunkSize);
                        var uploadedBytes = 0;
                        var nextPartNumber = 1;

                        async function uploadWorker() {
                            while (true) {
                                var partNumber = nextPartNumber;
                                nextPartNumber += 1;
                                if (partNumber > totalParts) {
                                    return;
                                }

                                var start = (partNumber - 1) * chunkSize;
                                var end = Math.min(start + chunkSize, file.size);
                                var chunk = file.slice(start, end);
                                var chunkPayload = new FormData();
                                chunkPayload.append('upload_id', uploadId);
                                chunkPayload.append('chunk_number', String(partNumber - 1));
                                chunkPayload.append('chunk', chunk, 'chunk_' + (partNumber - 1));

                                await postChunk(form.dataset.chunkUploadUrl, chunkPayload);

                                uploadedBytes += chunk.size;
                                var progress = Math.min(99, Math.round((uploadedBytes / file.size) * 100));
                                setUploadProgress(form, progress);
                                setUploadStatus(form, 'Subiendo: ' + progress + '%', false);
                            }
                        }

                        var workers = [];
                        var workerCount = Math.min(maxParallel, totalParts);
                        for (var index = 0; index < workerCount; index++) {
                            workers.push(uploadWorker());
                        }

                        await Promise.all(workers);
                        setUploadStatus(form, 'Finalizando carga...', false);
                        await postJson(form.dataset.chunkFinalizeUrl, { upload_id: uploadId });

                        setUploadProgress(form, 100);
                        setUploadStatus(form, 'Convenio registrado correctamente. Actualizando vista...', false);
                        window.location.reload();
                    } catch (error) {
                        if (uploadId) {
                            try {
                                await postJson(form.dataset.chunkAbortUrl, { upload_id: uploadId });
                            } catch (abortError) {
                            }
                        }

                        setUploadStatus(form, error.message || 'No se pudo registrar el convenio.', true);
                        submitBtn.disabled = false;
                        form.dataset.uploading = '0';
                    }
                });
            });

            // Rotar chevron al expandir/colapsar cada categoría
            document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (trigger) {
                var target = trigger.getAttribute('data-bs-target');
                var firstRow = document.querySelector(target);
                if (!firstRow) return;

                firstRow.addEventListener('show.bs.collapse', function () {
                    trigger.querySelector('.category-chevron').style.transform = 'rotate(180deg)';
                });
                firstRow.addEventListener('hide.bs.collapse', function () {
                    trigger.querySelector('.category-chevron').style.transform = 'rotate(0deg)';
                });
            });
        });
    </script>

    @if (old('form_context') === 'agreement')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalElement = document.getElementById('modalCreateProjectAgreement');
                if (!modalElement || typeof bootstrap === 'undefined') {
                    return;
                }

                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            });
        </script>
    @elseif ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalElement = document.getElementById('modalCreateWork');
                if (!modalElement || typeof bootstrap === 'undefined') {
                    return;
                }

                var modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
                modalInstance.show();
            });
        </script>
    @endif
@endpush
