@extends('layouts.app')

@section('page_title', 'Almacén — Pila SOLMAT')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Pila SOLMAT (Almacén)</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">
                        <i class="ri-inbox-2-line me-2 text-warning"></i>Pila SOLMAT — Almacén
                    </h4>
                    <p class="text-muted fs-12 mb-0 mt-1">
                        Solicitudes de Material enviadas a Almacén, listas para generar SOLCOM.
                    </p>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <button type="submit"
                            form="formCreateConsolidatedSolcom"
                            id="btnCreateConsolidatedSolcom"
                            class="btn btn-warning btn-sm"
                            disabled>
                        <i class="ri-stack-line me-1"></i>Crear SOLCOM consolidada
                        <span class="badge bg-light text-dark ms-1" id="selectedSolmatCount">0</span>
                    </button>
                    <a href="{{ route('material_requests.index') }}" class="btn btn-light btn-sm">
                        <i class="ri-arrow-left-line me-1"></i>Ver todas las SOLMAT
                    </a>
                </div>
            </div>

            {{-- Bandeja + Búsqueda --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('warehouse.solmat_pile') }}" class="row g-2 align-items-end">
                    <div class="col-12">
                        <label class="form-label form-label-sm mb-2">Bandeja</label>
                        <ul class="nav nav-tabs nav-justified" role="tablist" aria-label="Bandeja SOLMAT">
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('warehouse.solmat_pile', ['section' => 'entrada', 'search' => $search ?: null]) }}"
                                   class="nav-link {{ ($section ?? 'entrada') === 'entrada' ? 'active' : '' }}">
                                    <i class="ri-inbox-archive-line me-1"></i>
                                    Entrada
                                    <span class="badge rounded-pill bg-primary-subtle text-primary ms-1">{{ $entryCount ?? 0 }}</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('warehouse.solmat_pile', ['section' => 'salida', 'search' => $search ?: null]) }}"
                                   class="nav-link {{ ($section ?? '') === 'salida' ? 'active' : '' }}">
                                    <i class="ri-share-forward-line me-1"></i>
                                    Con salida
                                    <span class="badge rounded-pill bg-info-subtle text-info ms-1">{{ $outCount ?? 0 }}</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('warehouse.solmat_pile', ['section' => 'todas', 'search' => $search ?: null]) }}"
                                   class="nav-link {{ ($section ?? '') === 'todas' ? 'active' : '' }}">
                                    <i class="ri-stack-line me-1"></i>
                                    Todas
                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary ms-1">{{ ($entryCount ?? 0) + ($outCount ?? 0) }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <input type="hidden" name="section" value="{{ $section ?? 'entrada' }}">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control" placeholder="Folio, proyecto, zona…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search)
                            <a href="{{ route('warehouse.solmat_pile', ['section' => $section ?? 'entrada']) }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div id="solmatSelectionProjectError" class="alert alert-danger m-3 py-2 fs-12 d-none">
                    Solo puedes seleccionar SOLMAT del mismo proyecto para crear una SOLCOM consolidada.
                </div>
                <form id="formCreateConsolidatedSolcom"
                      method="GET"
                      action="{{ route('purchase_requests.create_from_solmat_multi') }}">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th style="width:44px;">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="selectAllSolmat"
                                           title="Seleccionar todo en esta página">
                                </th>
                                <th>Folio</th>
                                <th>Proyecto / Obras</th>
                                <th>Ubicación</th>
                                <th>Categoría</th>
                                <th>F. Solicitud</th>
                                <th>Ítems</th>
                                <th>Comprometido</th>
                                <th>SOLCOM Generadas</th>
                                <th>Fecha liberación</th>
                                <th>Elaborada por</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($materialRequests as $mr)
                                @php
                                    $committedPercent = $mr->committed_percent;
                                    $isFullyCommitted = $mr->total_requested_quantity > 0 && $committedPercent >= 100;
                                    $commitmentClass = $isFullyCommitted
                                        ? 'bg-danger-subtle text-danger'
                                        : ($committedPercent > 0 ? 'bg-warning-subtle text-warning' : 'bg-light text-muted border');
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                               class="form-check-input js-solmat-select"
                                               name="material_request_ids[]"
                                               value="{{ $mr->id }}"
                                               data-project-id="{{ (int) ($mr->project_id ?? 0) }}"
                                               data-project-name="{{ $mr->project?->name ?? 'Sin proyecto' }}"
                                               @disabled($isFullyCommitted)
                                               title="Seleccionar SOLMAT #{{ $mr->folio }}">
                                    </td>
                                    <td>
                                        <a href="{{ route('material_requests.show', $mr) }}"
                                           class="fw-semibold text-primary text-decoration-none">
                                            #{{ $mr->folio }}
                                        </a>
                                    </td>
                                    <td style="max-width:180px">
                                        @php
                                            $projectName = $mr->project?->name;
                                            $works = $mr->projectWorks;
                                        @endphp
                                        @if ($projectName)
                                            <div class="hover-marquee" style="--marquee-width:170px;" title="{{ $projectName }}">
                                                <span class="track"><span>{{ $projectName }}</span><span aria-hidden="true">{{ $projectName }}</span></span>
                                            </div>
                                        @endif
                                        @if ($works->isNotEmpty())
                                            @foreach ($works->take(2) as $pw)
                                                <small class="text-muted d-block hover-marquee" style="--marquee-width:170px;" title="{{ $pw->name }}">
                                                    <span class="track"><span>{{ $pw->name }}</span><span aria-hidden="true">{{ $pw->name }}</span></span>
                                                </small>
                                            @endforeach
                                            @if ($works->count() > 2)
                                                <small class="text-muted d-block">+{{ $works->count() - 2 }} más</small>
                                            @endif
                                        @endif
                                        @if (!$projectName && $works->isEmpty())—@endif
                                    </td>
                                    <td>{{ $mr->zone }}</td>
                                    <td>{{ $mr->supply_category }}</td>
                                    <td>{{ $mr->request_date?->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                            {{ $mr->items->count() }} ítem(s)
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $commitmentClass }} py-1 px-2 fs-12">
                                            {{ number_format($committedPercent, 0) }}%
                                        </span>
                                    </td>
                                    <td>
                                        @if (($mr->purchase_requests_count ?? 0) > 0)
                                            <span class="badge bg-info-subtle text-info py-1 px-2 fs-12">
                                                {{ $mr->purchase_requests_count }} SOLCOM
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border py-1 px-2 fs-12">Sin salida</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($mr->sent_to_warehouse_at)
                                            <span class="fw-medium fs-12 text-nowrap" title="{{ $mr->sent_to_warehouse_at->format('d/m/Y H:i') }}">
                                                {{ $mr->sent_to_warehouse_at->format('d/m/Y H:i') }}
                                            </span>
                                            <div><small class="text-muted">{{ $mr->sent_to_warehouse_at->diffForHumans() }}</small></div>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $mr->requestedBy?->name ?? '—' }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('material_requests.show', $mr) }}"
                                               class="btn btn-light btn-sm" title="Ver SOLMAT">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @if ($isFullyCommitted)
                                                <button type="button" class="btn btn-light btn-sm" disabled
                                                        title="Todos los conceptos de esta SOLMAT están comprometidos">
                                                    <i class="ri-lock-line me-1"></i>Sin saldo
                                                </button>
                                            @else
                                                <a href="{{ route('purchase_requests.create_from_solmat', $mr) }}"
                                                   class="btn btn-warning btn-sm" title="Crear SOLCOM">
                                                    <i class="ri-shopping-cart-2-line me-1"></i>Crear SOLCOM
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-5">
                                        <i class="ri-inbox-2-line fs-24 d-block mb-2 opacity-50"></i>
                                        No hay SOLMAT en esta bandeja.<br>
                                        <small>Cambia de sección o ajusta los filtros para ver más resultados.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </form>
            </div>

            @if ($materialRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $materialRequests->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const checkboxes = Array.from(document.querySelectorAll('.js-solmat-select'));
    const selectAll = document.getElementById('selectAllSolmat');
    const createButton = document.getElementById('btnCreateConsolidatedSolcom');
    const selectedCount = document.getElementById('selectedSolmatCount');
    const projectError = document.getElementById('solmatSelectionProjectError');
    const form = document.getElementById('formCreateConsolidatedSolcom');

    function getCheckedBoxes() {
        return checkboxes.filter(function (checkbox) { return !checkbox.disabled && checkbox.checked; });
    }

    function updateSelectionState() {
        const checked = getCheckedBoxes();

        if (selectedCount) {
            selectedCount.textContent = String(checked.length);
        }

        if (createButton) {
            createButton.disabled = checked.length === 0;
        }

        if (selectAll) {
            selectAll.checked = checked.length > 0 && checked.length === checkboxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
        }
    }

    function hideProjectError() {
        if (projectError) {
            projectError.classList.add('d-none');
        }
    }

    function showProjectError() {
        if (!projectError) {
            return;
        }

        projectError.classList.remove('d-none');
        projectError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function canSelectCheckbox(targetCheckbox) {
        const checked = getCheckedBoxes();
        if (checked.length === 0) {
            return true;
        }

        const expectedProjectId = String(checked[0].getAttribute('data-project-id') || '');
        const targetProjectId = String(targetCheckbox.getAttribute('data-project-id') || '');

        return expectedProjectId === targetProjectId;
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (checkbox.checked && !canSelectCheckbox(checkbox)) {
                checkbox.checked = false;
                showProjectError();
            } else {
                hideProjectError();
            }

            updateSelectionState();
        });
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            if (!selectAll.checked) {
                checkboxes.forEach(function (checkbox) { checkbox.checked = false; });
                hideProjectError();
                updateSelectionState();
                return;
            }

            const firstProjectId = checkboxes.length > 0
                ? String(checkboxes[0].getAttribute('data-project-id') || '')
                : '';

            checkboxes.forEach(function (checkbox) {
                const projectId = String(checkbox.getAttribute('data-project-id') || '');
                checkbox.checked = !checkbox.disabled && projectId === firstProjectId;
            });

            const hasDifferentProjects = checkboxes.some(function (checkbox) {
                return String(checkbox.getAttribute('data-project-id') || '') !== firstProjectId;
            });

            if (hasDifferentProjects) {
                showProjectError();
            } else {
                hideProjectError();
            }

            updateSelectionState();
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            const checked = getCheckedBoxes();

            if (checked.length === 0) {
                event.preventDefault();
                return;
            }

            const projectIds = checked
                .map(function (checkbox) { return String(checkbox.getAttribute('data-project-id') || ''); })
                .filter(function (value) { return value !== ''; });

            const uniqueProjectIds = Array.from(new Set(projectIds));
            if (uniqueProjectIds.length > 1) {
                event.preventDefault();
                showProjectError();
            }
        });
    }

    updateSelectionState();
}());
</script>
@endpush
