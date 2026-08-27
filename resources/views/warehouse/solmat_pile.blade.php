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

@php
    $persistedSelectedIds = collect(data_get($selectionState ?? [], 'selected_ids', []))
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values()
        ->all();
    $persistedSelectedCount = (int) data_get($selectionState ?? [], 'selected_count', count($persistedSelectedIds));
    $persistedSelectedProjectId = data_get($selectionState ?? [], 'selected_project_id');
@endphp

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
                    <button type="button"
                            id="btnClearSolmatSelection"
                            class="btn btn-outline-secondary btn-sm"
                            @disabled($persistedSelectedCount === 0)>
                        <i class="ri-delete-bin-line me-1"></i>Limpiar selección
                    </button>
                    <button type="submit"
                            form="formCreateConsolidatedSolcom"
                            id="btnCreateConsolidatedSolcom"
                            class="btn btn-warning btn-sm"
                            @disabled($persistedSelectedCount === 0)>
                        <i class="ri-stack-line me-1"></i>Crear SOLCOM consolidada
                        <span class="badge bg-light text-dark ms-1" id="selectedSolmatCount">{{ $persistedSelectedCount }}</span>
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
                                    $hasAvailableQuantity = $mr->hasAvailableQuantityForProjectWorks();
                                    $commitmentClass = $isFullyCommitted
                                        ? 'bg-danger-subtle text-danger'
                                        : ($committedPercent > 0 ? 'bg-warning-subtle text-warning' : 'bg-light text-muted border');
                                @endphp
                                <tr>
                                    <td>
                                        @php
                                            $isPersistedSelected = in_array((int) $mr->id, $persistedSelectedIds, true);
                                        @endphp
                                        <input type="checkbox"
                                               class="form-check-input js-solmat-select"
                                               name="material_request_ids[]"
                                               value="{{ $mr->id }}"
                                               data-project-id="{{ (int) ($mr->project_id ?? 0) }}"
                                               data-project-name="{{ $mr->project?->name ?? 'Sin proyecto' }}"
                                               @checked($isPersistedSelected && !$isFullyCommitted)
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
                                            {{--
                                            <a href="{{ route('material_requests.show', $mr) }}"
                                               class="btn btn-light btn-sm" title="Ver SOLMAT">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            --}}
                                            @hasanyrole('admin|suministros')
                                            @if ($mr->status === 'sent_to_warehouse')
                                                <button type="button"
                                                        class="btn btn-soft-primary btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalReviewCommitments{{ $mr->id }}"
                                                        title="Revisar compromisos">
                                                    <i class="ri-archive-stack-line me-1"></i>Revisar
                                                </button>
                                            @endif
                                            @endhasanyrole
                                            @if ($isFullyCommitted)
                                                <button type="button" class="btn btn-light btn-sm" disabled
                                                        title="Todos los conceptos de esta SOLMAT están comprometidos">
                                                    <i class="ri-lock-line me-1"></i>Sin saldo
                                                </button>
                                            @elseif (!$hasAvailableQuantity)
                                                <button type="button" class="btn btn-light btn-sm" disabled
                                                        title="Esta SOLMAT no tiene conceptos disponibles para crear una SOLCOM">
                                                    <i class="ri-file-forbid-line me-1"></i>Sin conceptos
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

@hasanyrole('admin|suministros')
@foreach ($materialRequests as $mr)
    @if ($mr->status === 'sent_to_warehouse')
    <div class="modal fade" id="modalReviewCommitments{{ $mr->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <form method="POST" action="{{ route('warehouse.solmat_pile.commitments.update', $mr) }}">
                    @csrf
                    <input type="hidden" name="review_material_request_id" value="{{ $mr->id }}">
                    @php
                        $legacyWork = $mr->projectWorks->count() === 1 ? $mr->projectWorks->first() : null;
                    @endphp
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title"><i class="ri-archive-stack-line me-2 text-primary"></i>Revisar compromisos SOLMAT #{{ $mr->folio }}</h5>
                            <p class="text-muted fs-12 mb-0 mt-1">Marca lo que Almacén puede surtir. La SOLCOM incluirá solo el saldo disponible.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th>Concepto</th>
                                        <th>Obra</th>
                                        <th class="text-end">Cantidad</th>
                                        <th>Última confirmación</th>
                                        <th class="text-center">Comprometido</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($mr->items as $item)
                                        @forelse ($item->workQuantities as $workQuantity)
                                            <tr>
                                                <td>
                                                    <span class="fw-semibold d-block">{{ $item->code }}</span>
                                                    <small class="text-muted">{{ $item->description }}</small>
                                                </td>
                                                <td>{{ $workQuantity->projectWork?->name ?? '—' }}</td>
                                                <td class="text-end fw-semibold">{{ number_format((float) $workQuantity->quantity, 2, '.', '') }}</td>
                                                <td>
                                                    @if ($workQuantity->is_committed && $workQuantity->committedBy)
                                                        <small class="d-block">{{ $workQuantity->committedBy->name }}</small>
                                                        <small class="text-muted">{{ $workQuantity->committed_at?->format('d/m/Y H:i') }}</small>
                                                    @elseif ($workQuantity->is_committed)
                                                        <small class="text-muted">Confirmado previamente</small>
                                                    @else
                                                        <small class="text-muted">Disponible para compra</small>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <input type="hidden"
                                                           name="commitments[{{ $item->id }}][{{ $workQuantity->project_work_id }}]"
                                                           value="0">
                                                    <input type="checkbox"
                                                           class="form-check-input"
                                                           id="commitment_{{ $mr->id }}_{{ $item->id }}_{{ $workQuantity->project_work_id }}"
                                                           name="commitments[{{ $item->id }}][{{ $workQuantity->project_work_id }}]"
                                                           value="1"
                                                           @checked($workQuantity->is_committed)>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td>
                                                    <span class="fw-semibold d-block">{{ $item->code }}</span>
                                                    <small class="text-muted">{{ $item->description }}</small>
                                                </td>
                                                <td>{{ $legacyWork?->name ?? 'Desglose pendiente' }}</td>
                                                <td class="text-end fw-semibold">{{ number_format((float) $item->quantity, 2, '.', '') }}</td>
                                                <td>
                                                    @if ($legacyWork)
                                                        <small class="text-muted">Concepto legacy; se asignará a la única obra.</small>
                                                    @else
                                                        <small class="text-danger">Requiere desglose por obra.</small>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($legacyWork)
                                                        <input type="hidden"
                                                               name="commitments[{{ $item->id }}][{{ $legacyWork->id }}]"
                                                               value="0">
                                                        <input type="checkbox"
                                                               class="form-check-input"
                                                               id="commitment_legacy_{{ $mr->id }}_{{ $item->id }}"
                                                               name="commitments[{{ $item->id }}][{{ $legacyWork->id }}]"
                                                               value="1">
                                                    @else
                                                        <input type="checkbox" class="form-check-input" disabled title="Este concepto requiere desglose por obra">
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforelse
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">La SOLMAT no tiene conceptos para revisar.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Guardar revisión</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endforeach
@endhasanyrole

@endsection

@push('scripts')
<script>
(function () {
    const initialSelectedIds = @json($persistedSelectedIds);
    const initialSelectedProjectId = @json($persistedSelectedProjectId);
    const syncUrl = @json(route('warehouse.solmat_pile.selection.sync'));
    const clearUrl = @json(route('warehouse.solmat_pile.selection.clear'));
    const csrfToken = @json(csrf_token());

    const checkboxes = Array.from(document.querySelectorAll('.js-solmat-select'));
    const selectAll = document.getElementById('selectAllSolmat');
    const createButton = document.getElementById('btnCreateConsolidatedSolcom');
    const clearButton = document.getElementById('btnClearSolmatSelection');
    const selectedCount = document.getElementById('selectedSolmatCount');
    const projectError = document.getElementById('solmatSelectionProjectError');
    const form = document.getElementById('formCreateConsolidatedSolcom');
    const selectedIds = new Set((initialSelectedIds || []).map(function (id) { return String(id); }));
    let selectedProjectId = initialSelectedProjectId ? String(initialSelectedProjectId) : null;

    function getProjectId(checkbox) {
        return String(checkbox.getAttribute('data-project-id') || '');
    }

    function getSelectableCheckboxesOnPage() {
        return checkboxes.filter(function (checkbox) {
            if (checkbox.disabled) {
                return false;
            }
            if (!selectedProjectId) {
                return true;
            }
            return getProjectId(checkbox) === selectedProjectId;
        });
    }

    function getCheckedBoxesOnPage() {
        return getSelectableCheckboxesOnPage().filter(function (checkbox) {
            return checkbox.checked;
        });
    }

    function applySelectedIdsToPage() {
        checkboxes.forEach(function (checkbox) {
            if (checkbox.disabled) {
                checkbox.checked = false;
                return;
            }

            checkbox.checked = selectedIds.has(String(checkbox.value));
        });
    }

    function updateSelectionState() {
        const checkedOnPage = getCheckedBoxesOnPage();
        const selectableOnPage = getSelectableCheckboxesOnPage();
        const count = selectedIds.size;

        if (selectedCount) {
            selectedCount.textContent = String(count);
        }

        if (createButton) {
            createButton.disabled = count === 0;
        }

        if (clearButton) {
            clearButton.disabled = count === 0;
        }

        if (selectAll) {
            if (selectableOnPage.length === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else {
                selectAll.checked = checkedOnPage.length > 0 && checkedOnPage.length === selectableOnPage.length;
                selectAll.indeterminate = checkedOnPage.length > 0 && checkedOnPage.length < selectableOnPage.length;
            }
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

    function setSelectedProjectIdFromCurrentSelection() {
        if (selectedIds.size === 0) {
            selectedProjectId = null;
            return;
        }

        if (selectedProjectId) {
            return;
        }

        const firstChecked = checkboxes.find(function (checkbox) {
            return checkbox.checked && !checkbox.disabled;
        });

        if (firstChecked) {
            selectedProjectId = getProjectId(firstChecked);
        }
    }

    function canSelectCheckbox(targetCheckbox) {
        if (!selectedProjectId) {
            return true;
        }

        return getProjectId(targetCheckbox) === selectedProjectId;
    }

    function syncSelectionToServer() {
        return fetch(syncUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                selected_ids: Array.from(selectedIds).map(function (id) { return Number(id); }),
                selected_project_id: selectedProjectId ? Number(selectedProjectId) : null
            })
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('No se pudo sincronizar la selección.');
                }

                return response.json();
            })
            .then(function (payload) {
                selectedIds.clear();
                (payload.selected_ids || []).forEach(function (id) {
                    selectedIds.add(String(id));
                });
                selectedProjectId = payload.selected_project_id ? String(payload.selected_project_id) : null;
                applySelectedIdsToPage();
                updateSelectionState();
            })
            .catch(function () {
                // Si falla la sincronización, se conserva estado local de página sin interrumpir flujo.
                updateSelectionState();
            });
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (checkbox.checked && !canSelectCheckbox(checkbox)) {
                checkbox.checked = false;
                showProjectError();
                updateSelectionState();
                return;
            }

            if (checkbox.checked) {
                selectedIds.add(String(checkbox.value));
                if (!selectedProjectId) {
                    selectedProjectId = getProjectId(checkbox);
                }
            } else {
                selectedIds.delete(String(checkbox.value));
                if (selectedIds.size === 0) {
                    selectedProjectId = null;
                }
            }

            hideProjectError();
            syncSelectionToServer();
        });
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            if (!selectAll.checked) {
                getSelectableCheckboxesOnPage().forEach(function (checkbox) {
                    checkbox.checked = false;
                    selectedIds.delete(String(checkbox.value));
                });

                if (selectedIds.size === 0) {
                    selectedProjectId = null;
                }

                hideProjectError();
                syncSelectionToServer();
                return;
            }

            const selectable = checkboxes.filter(function (checkbox) {
                return !checkbox.disabled;
            });

            if (selectable.length === 0) {
                updateSelectionState();
                return;
            }

            let targetProjectId = selectedProjectId;
            if (!targetProjectId) {
                targetProjectId = getProjectId(selectable[0]);
            }

            const pageProjectMatches = selectable.filter(function (checkbox) {
                return getProjectId(checkbox) === targetProjectId;
            });

            if (pageProjectMatches.length === 0) {
                showProjectError();
                selectAll.checked = false;
                updateSelectionState();
                return;
            }

            selectedProjectId = targetProjectId;

            checkboxes.forEach(function (checkbox) {
                if (checkbox.disabled) {
                    checkbox.checked = false;
                    return;
                }

                const shouldSelect = getProjectId(checkbox) === selectedProjectId;
                checkbox.checked = shouldSelect;

                if (shouldSelect) {
                    selectedIds.add(String(checkbox.value));
                }
            });

            hideProjectError();
            syncSelectionToServer();
        });
    }

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            fetch(clearUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('No se pudo limpiar la selección.');
                }

                selectedIds.clear();
                selectedProjectId = null;
                hideProjectError();
                applySelectedIdsToPage();
                updateSelectionState();
            }).catch(function () {
                updateSelectionState();
            });
        });
    }

    setSelectedProjectIdFromCurrentSelection();

    if (form) {
        form.addEventListener('submit', function (event) {
            if (selectedIds.size === 0) {
                event.preventDefault();
                return;
            }

            if (!selectedProjectId) {
                event.preventDefault();
                showProjectError();
                return;
            }

            Array.from(form.querySelectorAll('.js-solmat-hidden-id')).forEach(function (input) {
                input.remove();
            });

            Array.from(selectedIds).forEach(function (id) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'material_request_ids[]';
                input.value = id;
                input.className = 'js-solmat-hidden-id';
                form.appendChild(input);
            });
        });
    }

    applySelectedIdsToPage();
    updateSelectionState();
}());
</script>
@endpush
