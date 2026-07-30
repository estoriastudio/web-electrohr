@extends('layouts.app')

@section('page_title', 'Solicitudes de Compra (SOLCOM)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Solicitudes de Compra</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
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
                <h4 class="card-title mb-0">Solicitudes de Compra</h4>
                <div>
                    {{--  
                    @hasanyrole('admin|Solcom')
                    @can('create')
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalCreatePR">
                        <i class="ri-add-line me-1"></i> Nueva SOLCOM
                    </button>
                    @endcan
                    @endhasanyrole
                    --}}
                    <a href="{{ route('purchase_requests.archived') }}" class="btn btn-sm btn-outline-secondary ms-1" title="Ver archivadas">
                        <i class="ri-archive-line me-1"></i> Archivadas
                    </a>
                    @hasrole('admin')
                    <a href="{{ route('purchase_requests.soft_deleted') }}" class="btn btn-sm btn-outline-danger ms-1" title="Papelera">
                        <i class="ri-delete-bin-line me-1"></i> Papelera
                    </a>
                    @endhasrole
                </div>
            </div>

            {{-- Filtros --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('purchase_requests.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control" placeholder="Folio, proyecto, zona, descripción…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="pending"   {{ $status === 'pending'   ? 'selected' : '' }}>Pendiente</option>
                            <option value="linked"    {{ $status === 'linked'    ? 'selected' : '' }}>Ligado</option>
                            <option value="sent_to_purchasing" {{ $status === 'sent_to_purchasing' ? 'selected' : '' }}>En Compras</option>
                            <option value="changes_requested" {{ $status === 'changes_requested' ? 'selected' : '' }}>Cambios Solicitados</option>
                            <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Finalizado</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search || $status)
                            <a href="{{ route('purchase_requests.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @include('purchase_requests.utilities._table')
            </div>

            @if ($purchaseRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $purchaseRequests->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- MODAL — Nueva SOLCOM --}}
@hasanyrole('admin|Solcom')
@can('create')
<div class="modal fade" id="modalCreatePR" tabindex="-1" aria-labelledby="modalCreatePRLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <form action="{{ route('purchase_requests.store') }}" method="POST" id="formCreatePR">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreatePRLabel">
                        <i class="ri-shopping-cart-2-line me-2 text-primary"></i>Nueva Solicitud de Compra (SOLCOM)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Búsqueda SOLMAT --}}
                    <div class="alert alert-info d-flex align-items-center gap-3 mb-4 py-2">
                        <i class="ri-search-2-line fs-18 flex-shrink-0"></i>
                        <div class="flex-grow-1">
                            <p class="mb-1 fw-semibold fs-13">¿Tienes una Solicitud de Material (SOLMAT)?</p>
                            <div class="input-group input-group-sm" style="max-width:300px">
                                <input type="number" id="solmatFolioInput" class="form-control"
                                       placeholder="Folio SOLMAT…" min="1">
                                <button type="button" class="btn btn-primary" id="btnSearchSolmat">
                                    <i class="ri-search-line"></i> Buscar
                                </button>
                            </div>
                            <small id="solmatSearchMsg" class="text-danger d-none mt-1"></small>
                        </div>
                        <div id="solmatLinkedBadge" class="d-none">
                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">
                                <i class="ri-links-line me-1"></i>SOLMAT vinculada
                            </span>
                        </div>
                    </div>

                    {{-- Campo oculto para material_request_id --}}
                    <input type="hidden" name="material_request_id" id="materialRequestId">

                    <div class="row g-3">
                        {{-- Folio y Código --}}
                        <div class="col-md-4">
                            <label class="form-label">Folio <span class="text-danger">*</span></label>
                            <input type="number" name="folio" class="form-control @error('folio') is-invalid @enderror"
                                   value="{{ old('folio', $nextFolio) }}" required min="18000" readonly
                                   style="background-color: #f8f9fa;">
                            @error('folio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Código</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code') }}" placeholder="Ej. HR.IG.9150.004-F1">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Descripción corta --}}
                        <div class="col-12">
                            <label class="form-label">Descripción corta del proyecto-consecutivo <span class="text-danger">*</span></label>
                            <input type="text" name="short_description" id="prShortDescription"
                                   class="form-control @error('short_description') is-invalid @enderror"
                                   value="{{ old('short_description') }}" required
                                   placeholder="Ej. HERRAJE CONDUCTOR">
                            @error('short_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Proyecto / Obra --}}
                        <div class="col-md-6">
                            <label class="form-label">Proyecto <span class="text-danger">*</span></label>
                            <select name="project_id" id="prProjectId"
                                    class="form-select @error('project_id') is-invalid @enderror" required>
                                <option value="">— Selecciona proyecto —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Obra <span class="text-danger">*</span></label>
                            <select name="project_work_id" id="prProjectWorkId"
                                    class="form-select @error('project_work_id') is-invalid @enderror" required>
                                <option value="">— Selecciona obra —</option>
                            </select>
                            @error('project_work_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Zona y Dirección --}}
                        <div class="col-md-6">
                            <label class="form-label">Zona <span class="text-danger">*</span></label>
                            <input type="text" name="zone" id="prZone"
                                   class="form-control @error('zone') is-invalid @enderror"
                                   value="{{ old('zone') }}" required>
                            @error('zone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección de Entrega <span class="text-danger">*</span></label>
                            <input type="text" name="delivery_address" id="prDeliveryAddress"
                                   class="form-control @error('delivery_address') is-invalid @enderror"
                                   value="{{ old('delivery_address') }}" required>
                            @error('delivery_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Fechas --}}
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Solicitud <span class="text-danger">*</span></label>
                            <input type="date" name="request_date"
                                   class="form-control @error('request_date') is-invalid @enderror"
                                   value="{{ old('request_date', now()->format('Y-m-d')) }}" required>
                            @error('request_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Necesidad <span class="text-danger">*</span></label>
                            <input type="date" name="need_date"
                                   class="form-control @error('need_date') is-invalid @enderror"
                                   value="{{ old('need_date') }}" required>
                            @error('need_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Comprador asignado --}}
                        <div class="col-12">
                            <label class="form-label">Comprador Asignado</label>
                            <select name="assigned_to" id="prAssignedTo"
                                    class="form-select @error('assigned_to') is-invalid @enderror">
                                <option value="">— Sin asignar —</option>
                                @foreach ($purchasingUsers as $pu)
                                    <option value="{{ $pu->id }}" {{ old('assigned_to') == $pu->id ? 'selected' : '' }}>
                                        {{ $pu->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text" id="prAssignedToHint"></div>
                            @error('assigned_to') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- Preview de conceptos de SOLMAT (informativo) --}}
                    <div id="solmatItemsPreview" class="d-none mt-4">
                        <hr>
                        <p class="fw-semibold fs-13 mb-2">
                            <i class="ri-list-check me-1 text-primary"></i>
                            Conceptos a importar desde la SOLMAT
                            <small class="text-muted fw-normal">(se cargarán automáticamente al crear)</small>
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Unidad</th>
                                        <th class="text-end">Cant. Solicitada</th>
                                    </tr>
                                </thead>
                                <tbody id="solmatItemsBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i>Crear SOLCOM
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endhasanyrole

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var projectSelect = document.getElementById('prProjectId');
    var workSelectEl = document.getElementById('prProjectWorkId');
    var btnSearch = document.getElementById('btnSearchSolmat');

    if (!projectSelect || !workSelectEl || !btnSearch) {
        return;
    }

// ── Cascade proyecto → obras ──────────────────────────────────────────────
function loadWorks(projectId, workSelect, selectValue) {
    if (!projectId) {
        workSelect.innerHTML = '<option value="">— Selecciona obra —</option>';
        return;
    }
    workSelect.innerHTML = '<option value="">— Cargando… —</option>';
    fetch(`/proyectos/${projectId}/obras-json`)
        .then(r => r.json())
        .then(works => {
            workSelect.innerHTML = '<option value="">— Selecciona obra —</option>';
            works.forEach(w => {
                const opt = document.createElement('option');
                opt.value = w.id;
                opt.textContent = w.name;
                if (selectValue && w.id == selectValue) opt.selected = true;
                workSelect.appendChild(opt);
            });
        })
        .catch(() => { workSelect.innerHTML = '<option value="">— Error —</option>'; });
}

projectSelect.addEventListener('change', function () {
    loadWorks(this.value, workSelectEl, null);
});

// ── Búsqueda de SOLMAT por folio ──────────────────────────────────────────
btnSearch.addEventListener('click', function () {
    const folio   = document.getElementById('solmatFolioInput').value.trim();
    const msgEl   = document.getElementById('solmatSearchMsg');
    const badgeEl = document.getElementById('solmatLinkedBadge');

    if (!folio) {
        msgEl.textContent = 'Ingresa un folio.';
        msgEl.classList.remove('d-none');
        return;
    }

    msgEl.classList.add('d-none');
    badgeEl.classList.add('d-none');
    document.getElementById('solmatItemsPreview').classList.add('d-none');

    fetch(`/solicitudes-material/buscar?folio=${folio}`)
        .then(r => r.ok ? r.json() : r.json().then(e => Promise.reject(e)))
        .then(data => {
            // Pre-llenar campos
            document.getElementById('materialRequestId').value = data.id;

            const projectSel = document.getElementById('prProjectId');
            const workSel    = document.getElementById('prProjectWorkId');

            // Seleccionar proyecto y luego cargar obras
            for (let opt of projectSel.options) {
                if (opt.value == data.project_id) { opt.selected = true; break; }
            }
            loadWorks(data.project_id, workSel, data.project_work_id);

            document.getElementById('prZone').value            = data.zone            ?? '';
            document.getElementById('prDeliveryAddress').value = data.delivery_address ?? '';
            document.getElementById('prShortDescription').value = data.supply_category ?? '';

            // Preview de ítems
            const tbody = document.getElementById('solmatItemsBody');
            tbody.innerHTML = '';
            if (data.items && data.items.length) {
                data.items.forEach((item, i) => {
                    tbody.innerHTML += `<tr>
                        <td class="fw-semibold">${item.code}</td>
                        <td>${item.description}</td>
                        <td>${item.unit}</td>
                        <td class="text-end">${parseFloat(item.quantity).toFixed(2)}</td>
                    </tr>`;
                });
                document.getElementById('solmatItemsPreview').classList.remove('d-none');
            }

            badgeEl.classList.remove('d-none');

            // Precargar comprador sugerido
            const assignedSel  = document.getElementById('prAssignedTo');
            const assignedHint = document.getElementById('prAssignedToHint');
            if (data.suggested_buyer_id && assignedSel) {
                assignedSel.value = data.suggested_buyer_id;
                if (assignedHint) {
                    assignedHint.innerHTML =
                        '<i class="ri-user-received-line me-1 text-success"></i>'
                        + 'Sugerido por categoría de conceptos: <strong>' + data.suggested_buyer_name + '</strong>';
                }
            } else if (assignedHint) {
                assignedHint.textContent = '';
            }
        })
        .catch(err => {
            msgEl.textContent = err.error ?? 'No se encontró la SOLMAT.';
            msgEl.classList.remove('d-none');
            document.getElementById('materialRequestId').value = '';
        });
});
});

@if ($errors->any())
    document.addEventListener('DOMContentLoaded', () => {
        var modalEl = document.getElementById('modalCreatePR');
        if (modalEl) {
            new bootstrap.Modal(modalEl).show();
        }
    });
@endif
</script>
@endpush
