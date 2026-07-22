@extends('layouts.app')

@php use Illuminate\Support\Facades\Storage; @endphp

@section('page_title', 'SOLMAT #' . $materialRequest->folio)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('material_requests.index') }}">Solicitudes de Material</a></li>
    <li class="breadcrumb-item active">SOLMAT #{{ $materialRequest->folio }}</li>
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

@php
    $statusMap = [
        'pending'           => ['label' => 'Pendiente',         'class' => 'bg-warning-subtle text-warning'],
        'sent_to_warehouse' => ['label' => 'Enviado a Almacén', 'class' => 'bg-secondary-subtle text-secondary'],
        'linked'            => ['label' => 'Ligado',            'class' => 'bg-info-subtle text-info'],
        'completed'         => ['label' => 'Finalizado',        'class' => 'bg-success-subtle text-success'],
    ];
    $s = $statusMap[$materialRequest->status] ?? ['label' => $materialRequest->status, 'class' => 'bg-secondary-subtle text-secondary'];
@endphp

{{-- ── Header ── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">
                            <i class="ri-file-list-3-line me-2 text-primary"></i>
                            Solicitud de Material
                            <span class="text-primary fw-bold">Folio #{{ $materialRequest->folio }}</span>
                        </h4>
                        @if ($materialRequest->code)
                            <p class="text-muted mb-1 fs-13">Código: {{ $materialRequest->code }}</p>
                        @endif
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                <i class="ri-calendar-event-line me-1"></i>
                                Solicitud: {{ $materialRequest->request_date?->format('d/m/Y') }}
                            </span>
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                <i class="ri-alarm-line me-1"></i>
                                Necesidad: {{ $materialRequest->need_date?->format('d/m/Y') }}
                            </span>
                        </div>

                        <div class="mt-2">
                            @include('material_requests.partials._process_map')
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('material_requests.pdf', $materialRequest) }}"
                           class="btn btn-sm btn-outline-danger" target="_blank">
                            <i class="ri-file-pdf-2-line me-1"></i> Descargar PDF
                        </a>
                        
                        @hasanyrole('admin|Solmat')
                        <a href="{{ route('material_requests.edit', $materialRequest) }}"
                           class="btn btn-soft-primary btn-sm">
                            <i class="ri-edit-line me-1"></i>Editar
                        </a>
                        @if ($materialRequest->status === 'pending')
                        <form action="{{ route('material_requests.send_to_warehouse', $materialRequest) }}"
                              method="POST" onsubmit="return confirm('¿Enviar SOLMAT #{{ $materialRequest->folio }} a Almacén?')">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="ri-send-plane-2-line me-1"></i>Enviar a Almacén
                            </button>
                        </form>
                        @endif
                        @endhasanyrole
                        <a href="{{ route('material_requests.index') }}" class="btn btn-light btn-sm">
                            <i class="ri-arrow-left-line me-1"></i>Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Datos Generales ── --}}
<div class="row mb-3">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-information-line me-2 text-primary"></i>Datos Generales</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Proyecto</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->project?->name ?? '—' }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Obras</p>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse ($materialRequest->projectWorks as $pw)
                                <span class="badge bg-info-subtle text-info border py-1 px-2">{{ $pw->name }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Ubicación</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->zone }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Dirección de Entrega</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->delivery_address }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Categoría de Suministros</p>
                        <p class="fw-semibold mb-0">
                            {{ $materialRequest->conceptCategory?->name ?? $materialRequest->supply_category ?? '—' }}
                        </p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Solicitud Elaborada Por</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->requestedBy?->name ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SOLCOMs vinculadas --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-links-line me-2 text-info"></i>SOLCOMs Vinculadas</h5>
            </div>
            <div class="card-body p-0">
                @forelse ($materialRequest->purchaseRequests as $pr)
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <span class="fw-semibold">Folio #{{ $pr->folio }}</span>
                        <a href="{{ route('purchase_requests.show', $pr) }}" class="btn btn-light btn-sm">
                            <i class="ri-eye-line"></i>
                        </a>
                    </div>
                @empty
                    <p class="text-muted text-center py-3 mb-0 fs-13">Sin solicitudes de compra vinculadas.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ── Conceptos ── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="ri-list-check me-2 text-primary"></i>Conceptos</h5>
                <span id="solmat_items_count" class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                    {{ $materialRequest->items->count() }} ítem(s)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Unidad</th>
                                <th class="text-end">Cantidad</th>
                                <th>Especificaciones</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="solmat_items_tbody">
                            @forelse ($materialRequest->items as $index => $item)
                                @php
                                    $breakdownJson = $item->workQuantities->map(fn ($workQuantity) => [
                                        'work_id' => $workQuantity->project_work_id,
                                        'work_name' => $workQuantity->projectWork?->name,
                                        'quantity' => number_format((float) $workQuantity->quantity, 2, '.', ''),
                                    ])->values();
                                @endphp
                                <tr class="solmat-item-row" data-item-id="{{ $item->id }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="fw-semibold">{{ $item->code }}</span></td>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td class="text-end fw-semibold">
                                        <button type="button"
                                                class="btn btn-link btn-sm p-0 text-decoration-none text-dark js-solmat-qty-popover"
                                                data-breakdown='@json($breakdownJson)'
                                                title="Desglose por obra">
                                            <span class="d-inline-flex align-items-center gap-1">
                                                <span>{{ number_format((float) $item->total_quantity, 2, '.', '') }}</span>
                                                <i class="ri-information-line fs-13 text-primary"></i>
                                            </span>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @if ($item->file_path)
                                                <a href="{{ Storage::disk('s3')->temporaryUrl($item->file_path, now()->addMinutes(30)) }}"
                                                   target="_blank"
                                                   class="btn btn-light btn-sm"
                                                   title="Descargar especificaciones">
                                                    <i class="ri-file-download-line"></i>
                                                </a>
                                            @else
                                                <span class="text-muted fs-12">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @hasanyrole('admin|Solmat')
                                        <div class="d-flex gap-1">
                                            @if ($item->workQuantities->isNotEmpty())
                                                <button type="button"
                                                        class="btn btn-soft-primary btn-sm js-solmat-open-edit"
                                                        data-edit-target="#solmat_breakdown_edit_{{ $item->id }}"
                                                        title="Editar cantidades">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                            @endif
                                            <form action="{{ route('material_requests.items.destroy', [$materialRequest, $item]) }}"
                                                  method="POST"
                                                  class="solmat-delete-form">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </div>
                                        @endhasanyrole
                                    </td>
                                </tr>
                                @if ($item->workQuantities->isNotEmpty())
                                    @hasanyrole('admin|Solmat')
                                        <tr class="bg-light-subtle solmat-breakdown-row d-none" id="solmat_breakdown_edit_{{ $item->id }}">
                                            <td colspan="7" class="py-2">
                                                <form action="{{ route('material_requests.items.update', [$materialRequest, $item]) }}"
                                                      method="POST"
                                                      class="solmat-breakdown-form">
                                                    @csrf @method('PATCH')
                                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                        <span class="text-muted fs-12 fw-semibold">Editar cantidades por obra</span>
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            Total: <span class="js-solmat-breakdown-total">{{ number_format((float) $item->total_quantity, 2, '.', '') }}</span>
                                                        </span>
                                                    </div>
                                                    <div class="row g-2">
                                                        @foreach ($item->workQuantities as $workQuantity)
                                                            <div class="col-md-6 col-lg-4">
                                                                <label class="form-label fs-12 mb-1">{{ $workQuantity->projectWork?->name }}</label>
                                                                <input type="number"
                                                                       name="work_quantities[{{ $loop->index }}][quantity]"
                                                                       class="form-control js-solmat-breakdown-qty"
                                                                       value="{{ number_format((float) $workQuantity->quantity, 2, '.', '') }}"
                                                                       min="0.01"
                                                                       step="0.01"
                                                                       inputmode="decimal">
                                                                <input type="hidden"
                                                                       name="work_quantities[{{ $loop->index }}][work_id]"
                                                                       value="{{ $workQuantity->project_work_id }}">
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <div class="d-flex justify-content-end gap-2 mt-3">
                                                        <button type="button" class="btn btn-light btn-sm js-solmat-cancel-edit">
                                                            Cancelar
                                                        </button>
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            Guardar cambios
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    @endhasanyrole
                                @endif
                            @empty
                                <tr id="solmat_empty_row">
                                    <td colspan="7" class="text-center text-muted py-3">
                                        Sin conceptos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
            {{-- ── Panel Agregar Concepto (siempre visible, mobile-first) ── --}}
            @hasanyrole('admin|Solmat')
            <div class="border-top px-3 py-3" id="solmat_add_panel">

                {{-- Estado A: Búsqueda --}}
                <div id="solmat_state_search">
                    <p class="text-muted fs-12 mb-2 fw-medium">
                        <i class="ri-add-circle-line me-1 text-primary"></i>Agregar concepto
                    </p>
                    <div class="position-relative">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="ri-search-line text-muted"></i>
                            </span>
                            <input type="text"
                                   id="solmat_concept_search"
                                   class="form-control border-start-0 ps-0"
                                   placeholder="Buscar por código o descripción…"
                                   autocomplete="off"
                                   inputmode="text">
                        </div>
                        <ul id="solmat_concept_dropdown"
                            class="list-group position-absolute w-100 shadow d-none"
                            style="top:100%;left:0;max-height:280px;overflow-y:auto;z-index:1050"></ul>
                    </div>
                </div>

                {{-- Estado B: Concepto seleccionado --}}
                <div id="solmat_state_selected" class="d-none">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-success fs-13 fw-medium">
                            <i class="ri-checkbox-circle-line me-1"></i>Concepto seleccionado
                        </span>
                        <button type="button" id="solmat_btn_change"
                                class="btn btn-link btn-sm p-0 text-muted text-decoration-none">
                            <i class="ri-close-line me-1"></i>Cambiar
                        </button>
                    </div>

                    <div class="rounded-2 border bg-primary-subtle p-3 mb-3">
                        <p class="fw-bold mb-1 fs-15" id="solmat_preview_code"></p>
                        <p class="mb-2 text-body-secondary lh-sm" id="solmat_preview_desc"></p>
                        <span class="badge bg-white text-dark border" id="solmat_preview_unit"></span>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-medium mb-0">Cantidad por obra <span class="text-danger">*</span></label>
                            <span class="badge bg-primary-subtle text-primary" id="solmat_total_preview">Total: 0.00</span>
                        </div>
                        <div id="solmat_works_quantities" class="vstack gap-2"></div>
                    </div>

                    <div class="mb-3">
                        <label for="solmat_spec_file" class="form-label fw-medium">
                            <i class="ri-attachment-2 me-1 text-muted"></i>Especificaciones técnicas
                            <span class="text-muted fw-normal fs-12">(opcional)</span>
                        </label>
                        <input type="file"
                               id="solmat_spec_file"
                               class="form-control"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <div class="form-text">PDF, Word, Excel o imagen. Máx. 10 MB.</div>
                    </div>

                    <div id="solmat_add_error" class="text-danger fs-12 mb-2 d-none"></div>
                    <div class="d-grid">
                        <button type="button" id="solmat_btn_add" class="btn btn-primary btn-lg">
                            <i class="ri-add-line me-1"></i>Agregar a la solicitud
                        </button>
                    </div>
                </div>

            </div>
            @endhasanyrole

        </div>
    </div>
</div>

{{-- ── Observaciones ── --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-chat-3-line me-2 text-primary"></i>Observaciones</h5>
            </div>
            <div class="card-body">
                {{-- Lista de notas --}}
                @forelse ($materialRequest->observations ?? [] as $noteIndex => $note)
                    <div class="d-flex gap-3 mb-3">
                        <div class="avatar-sm shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-14 fw-bold">
                                {{ strtoupper(substr($note['user_name'] ?? '?', 0, 1)) }}
                            </span>
                        </div>
                        <div class="grow">
                            <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                                <span class="fw-semibold fs-13">{{ $note['user_name'] ?? 'Usuario' }}</span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted fs-12">{{ \Carbon\Carbon::parse($note['created_at'])->format('d/m/Y H:i') }}</span>
                                    @hasanyrole('admin|Solmat')
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#solmat_note_edit_{{ $noteIndex }}"
                                            aria-expanded="false"
                                            aria-controls="solmat_note_edit_{{ $noteIndex }}">
                                        Editar
                                    </button>
                                    <form action="{{ route('material_requests.notes.destroy', [$materialRequest, $noteIndex]) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Eliminar esta observación?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger text-decoration-none">
                                            Eliminar
                                        </button>
                                    </form>
                                    @endhasanyrole
                                </div>
                            </div>
                            <p class="mb-0 text-muted">{{ $note['text'] }}</p>

                            @hasanyrole('admin|Solmat')
                            <div class="collapse mt-2" id="solmat_note_edit_{{ $noteIndex }}">
                                <form action="{{ route('material_requests.notes.update', [$materialRequest, $noteIndex]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <div class="input-group input-group-sm">
                                        <textarea name="text" rows="2" class="form-control" required>{{ $note['text'] }}</textarea>
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    </div>
                                </form>
                            </div>
                            @endhasanyrole
                        </div>
                    </div>
                    <hr class="my-2">
                @empty
                    <p class="text-muted fs-13 mb-3">Sin observaciones registradas.</p>
                @endforelse

                {{-- Agregar nota --}}
                @hasanyrole('admin|Solmat')
                <form action="{{ route('material_requests.notes.store', $materialRequest) }}" method="POST" class="mt-3">
                    @csrf
                    <div class="mb-2">
                        <textarea name="text" rows="3"
                                  class="form-control @error('text') is-invalid @enderror"
                                  placeholder="Escribe una observación…" required>{{ old('text') }}</textarea>
                        @error('text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ri-send-plane-line me-1"></i>Agregar observación
                        </button>
                    </div>
                </form>
                @endhasanyrole
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<style>
@keyframes solmat-flash {
    0%   { background-color: rgba(var(--bs-primary-rgb), .12); }
    100% { background-color: transparent; }
}
.solmat-item-new { animation: solmat-flash .9s ease-out forwards; }

/* Popover "Desglose por obra": tabla compacta con nombres truncados */
.solmat-breakdown-popover { max-width: 320px; }
.solmat-breakdown-popover .popover-body { padding: .5rem .25rem; }
.solmat-breakdown-table { font-size: .8125rem; margin: 0; }
.solmat-breakdown-table th,
.solmat-breakdown-table td { padding: .2rem .5rem; }
.solmat-breakdown-table thead th {
    font-size: .6875rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    border-bottom: 1px solid var(--bs-border-color);
}
.solmat-breakdown-name { max-width: 200px; }
</style>
<script>
    // Configuración inyectada desde el servidor para material_request.js
    window.solmatConfig = {
        storeUrl: '{{ route('material_requests.items.store', $materialRequest) }}',
        searchUrl: '{{ route('concepts.search') }}?type=materiales&paginated=1&per_page=50',
        allowedWorks: @json($materialRequest->projectWorks->map(fn($pw) => ['id' => (string) $pw->id, 'name' => $pw->name])->values()),
    };
</script>
<script src="{{ asset('assets/js/material_request.js') }}"></script>
@endpush
