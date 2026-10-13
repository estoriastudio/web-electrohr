@extends('layouts.app')

@section('page_title', $project->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('my-projects.index') }}">Mis proyectos</a></li>
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
        <div class="fw-semibold mb-1">No se pudo guardar. Revisa los campos marcados.</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@php
    $engineerView = true;
    if ($errors->any() && old('delivery_address') !== null) {
        $activeTab = 'solmat';
    }
    $projectStatusMap = [
        'active'   => ['label' => 'Activo',   'class' => 'bg-success-subtle text-success'],
        'inactive' => ['label' => 'Inactivo', 'class' => 'bg-warning-subtle text-warning'],
    ];
    $ps = $projectStatusMap[$project->status] ?? ['label' => $project->status, 'class' => 'bg-secondary-subtle text-secondary'];
    $workStatusMap = [
        'active'   => ['label' => 'Activa',   'class' => 'bg-success-subtle text-success'],
        'inactive' => ['label' => 'Inactiva', 'class' => 'bg-warning-subtle text-warning'],
    ];
    $tabs = [
        'estimates' => ['label' => 'Estimaciones y Obras', 'icon' => 'ri-funds-line',          'count' => $project->estimates->count() + $project->works->count()],
        'solmat'    => ['label' => 'SOLMAT',               'icon' => 'ri-file-add-line',       'count' => $materialRequests->total()],
        'solcom'    => ['label' => 'SOLCOM',               'icon' => 'ri-shopping-cart-2-line', 'count' => $purchaseRequests->total()],
        'oc'        => ['label' => 'Órdenes de Compra',    'icon' => 'ri-file-list-3-line',    'count' => $purchaseOrders->total()],
    ];
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
                        @if ($project->manager)
                            &nbsp;·&nbsp;<i class="ri-user-star-line me-1"></i>Gerente: {{ $project->manager->name }}
                        @endif
                        @if ($project->city || $project->state)
                            &nbsp;·&nbsp;<i class="ri-map-pin-line me-1"></i>{{ implode(', ', array_filter([$project->city, $project->state])) }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-center">
                    <div class="fw-semibold fs-18 text-primary">{{ $project->works->count() }}</div>
                    <div class="text-muted fs-12">Obras</div>
                </div>
                <div class="text-center">
                    <div class="fw-semibold fs-18 text-primary">{{ $project->currency }} {{ number_format((float) ($project->project_value ?? 0), 2) }}</div>
                    <div class="text-muted fs-12">Valor</div>
                </div>
                <span class="badge {{ $ps['class'] }} py-1 px-3 fs-12">{{ $ps['label'] }}</span>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs" id="myProjectTabs" role="tablist">
    @foreach ($tabs as $key => $tab)
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === $key ? 'active' : '' }}" id="tab-{{ $key }}-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}"
                    type="button" role="tab">
                <i class="{{ $tab['icon'] }} me-1"></i> {{ $tab['label'] }}
                <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $tab['count'] }}</span>
            </button>
        </li>
    @endforeach
</ul>

<div class="tab-content mt-0 pt-0" id="myProjectTabsContent">

    {{-- Estimaciones y obras --}}
    <div class="tab-pane fade {{ $activeTab === 'estimates' ? 'show active' : '' }}" id="tab-estimates" role="tabpanel">
        <div class="pt-3">
            @include('projects._estimates')

            <div class="card mb-3">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0"><i class="ri-building-2-line me-1 text-muted"></i> Obras asociadas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                            <thead class="bg-light-subtle">
                                <tr>
                                    <th>Obra</th>
                                    <th>Supervisor</th>
                                    <th>Residente</th>
                                    <th>Contrato</th>
                                    <th class="text-end">Valor</th>
                                    <th>Vigencia</th>
                                    <th class="text-center"># OC</th>
                                    <th>Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($project->works as $work)
                                    @php $ws = $workStatusMap[$work->status] ?? ['label' => $work->status, 'class' => 'bg-secondary-subtle text-secondary']; @endphp
                                    <tr>
                                        <td class="fw-medium text-dark">{{ $work->name }}</td>
                                        <td>{{ $work->supervisorUser?->name ?? $work->supervisor ?? '—' }}</td>
                                        <td>{{ $work->residentUser?->name ?? $work->resident ?? '—' }}</td>
                                        <td>{{ $work->contract_number ?: '—' }}</td>
                                        <td class="text-end">
                                            @if ($work->contract_value)
                                                {{ $work->currency }} {{ number_format((float) str_replace(',', '', $work->contract_value), 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-muted fs-13">
                                            {{ $work->contract_start_date ? \Carbon\Carbon::parse($work->contract_start_date)->format('d/m/Y') : '—' }}
                                            &rarr;
                                            {{ $work->contract_end_date ? \Carbon\Carbon::parse($work->contract_end_date)->format('d/m/Y') : '—' }}
                                        </td>
                                        <td class="text-center"><span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $work->purchase_orders_count }}</span></td>
                                        <td><span class="badge {{ $ws['class'] }} py-1 px-2 fs-12">{{ $ws['label'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="ri-building-2-line fs-24 d-block mb-1"></i>
                                            Este proyecto aún no tiene obras registradas.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SOLMAT --}}
    <div class="tab-pane fade {{ $activeTab === 'solmat' ? 'show active' : '' }}" id="tab-solmat" role="tabpanel">
        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Solicitudes de Material</h5>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted fs-13">{{ $materialRequests->total() }} registros</span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateMR">
                        <i class="ri-add-line me-1"></i> Nueva SOLMAT
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                @include('material_requests.utilities._table')
            </div>
            @if ($materialRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">{{ $materialRequests->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>

    {{-- SOLCOM --}}
    <div class="tab-pane fade {{ $activeTab === 'solcom' ? 'show active' : '' }}" id="tab-solcom" role="tabpanel">
        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Solicitudes de Compra</h5>
                <span class="text-muted fs-13">{{ $purchaseRequests->total() }} registros</span>
            </div>
            <div class="card-body p-0">
                @include('purchase_requests.utilities._table')
            </div>
            @if ($purchaseRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">{{ $purchaseRequests->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>

    {{-- Órdenes de compra --}}
    <div class="tab-pane fade {{ $activeTab === 'oc' ? 'show active' : '' }}" id="tab-oc" role="tabpanel">
        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Órdenes de Compra</h5>
                <span class="text-muted fs-13">{{ $purchaseOrders->total() }} registros</span>
            </div>
            <div class="card-body p-0">
                @include('purchase_orders.utilities._table', ['orders' => $purchaseOrders])
            </div>
            @if ($purchaseOrders->hasPages())
                <div class="card-footer d-flex justify-content-end">{{ $purchaseOrders->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>

</div>

{{-- MODAL — Nueva SOLMAT (proyecto fijo) --}}
<div class="modal fade" id="modalCreateMR" tabindex="-1" aria-labelledby="modalCreateMRLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('material_requests.store') }}" method="POST">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateMRLabel">
                        <i class="ri-file-add-line me-2 text-primary"></i>Nueva Solicitud de Material (SOLMAT)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Folio</label>
                            <div class="form-control" style="background-color: #f8f9fa; color: #6c757d;">Se asignará automáticamente</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Código</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code') }}" placeholder="Ej. HR.IG.9150.004-F1">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Proyecto</label>
                            <input type="text" class="form-control bg-light" value="{{ $project->name }}" readonly>
                            @error('project_id') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Obras <span class="text-danger">*</span></label>
                            <select name="project_work_ids[]" id="mrProjectWorkId"
                                    class="form-select @error('project_work_ids') is-invalid @enderror" multiple required>
                                @foreach ($project->works->where('status', 'active') as $work)
                                    <option value="{{ $work->id }}" @selected(in_array($work->id, (array) old('project_work_ids', [])))>{{ $work->name }}</option>
                                @endforeach
                            </select>
                            @error('project_work_ids') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Selecciona una o más obras vinculadas.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Tipo de ubicación</label>
                            <select name="location_type" class="form-select">
                                <option value="sitio" @selected(old('location_type', 'sitio') !== 'electrohr')>Sitio</option>
                                <option value="electrohr" @selected(old('location_type') === 'electrohr')>Electro HR</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Ubicación <span class="text-danger">*</span></label>
                            <input type="text" name="zone" class="form-control @error('zone') is-invalid @enderror"
                                   value="{{ old('zone') }}" required placeholder="Nombre o pega un enlace de Google Maps…">
                            @error('zone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Dirección de entrega <span class="text-danger">*</span></label>
                            <input type="text" name="delivery_address" class="form-control @error('delivery_address') is-invalid @enderror"
                                   value="{{ old('delivery_address') }}" required>
                            @error('delivery_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fecha de Solicitud <span class="text-danger">*</span></label>
                            <input type="date" name="request_date" class="form-control @error('request_date') is-invalid @enderror"
                                   value="{{ old('request_date', now()->format('Y-m-d')) }}" required>
                            @error('request_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Necesidad <span class="text-danger">*</span></label>
                            <input type="date" name="need_date" class="form-control @error('need_date') is-invalid @enderror"
                                   value="{{ old('need_date') }}" required>
                            @error('need_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Categoría de Suministros <span class="text-danger">*</span></label>
                            <select name="concept_category_id" id="mrCategoryId"
                                    class="form-select @error('concept_category_id') is-invalid @enderror" required>
                                <option value="">— Selecciona una categoría —</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('concept_category_id') == $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            @error('concept_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Crear SOLMAT</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('modalCreateMR');
    var workChoices = null;
    var categoryChoices = null;

    modalEl.addEventListener('shown.bs.modal', function () {
        if (!workChoices) {
            workChoices = new Choices(document.getElementById('mrProjectWorkId'), {
                searchEnabled: true, searchPlaceholderValue: 'Buscar obra…', itemSelectText: '',
                noResultsText: 'Sin resultados', noChoicesText: 'Sin obras disponibles',
                removeItemButton: true, placeholder: true, placeholderValue: '— Selecciona una o más obras —',
            });
        }
        if (!categoryChoices) {
            categoryChoices = new Choices(document.getElementById('mrCategoryId'), {
                searchEnabled: true, searchPlaceholderValue: 'Buscar categoría...', itemSelectText: '',
                noResultsText: 'Sin resultados', noChoicesText: 'Sin categorías disponibles',
            });
        }
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (workChoices) { workChoices.destroy(); workChoices = null; }
        if (categoryChoices) { categoryChoices.destroy(); categoryChoices = null; }
    });

    @if ($errors->any() && old('delivery_address') !== null)
        new bootstrap.Modal(modalEl).show();
    @endif
});
</script>
@endpush

@endsection
