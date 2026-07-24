@extends('layouts.app')

@section('page_title', 'SOLMAT — Papelera')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('material_requests.index') }}">Solicitudes de Material</a></li>
    <li class="breadcrumb-item active">Papelera</li>
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

<div class="row">
    <div class="col-xl-12">
        <div class="card border-danger">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom bg-danger-subtle">
                <div>
                    <h4 class="card-title mb-0 text-danger">
                        <i class="ri-delete-bin-line me-2"></i>Papelera — SOLMATs eliminadas
                    </h4>
                    <small class="text-muted">Solo los administradores pueden eliminar permanentemente o restaurar estos registros.</small>
                </div>
                <a href="{{ route('material_requests.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Volver al listado activo
                </a>
            </div>

            {{-- Barra de búsqueda --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('material_requests.soft_deleted') }}" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por folio, proyecto, zona o categoría…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search)
                            <a href="{{ route('material_requests.soft_deleted') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @include('material_requests.utilities._table')
            </div>

            @if ($materialRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $materialRequests->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="modalForceDestroySolmat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger-subtle">
                <h5 class="modal-title text-danger">
                    <i class="ri-alert-line me-2"></i>Cancelar trazabilidad de SOLMAT
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    Estás a punto de eliminar permanentemente la SOLMAT
                    <strong id="forceDestroySolmatFolio">#—</strong>.
                </p>
                <p class="text-muted fs-13 mb-3">
                    Esta acción cancela todo el proceso de trazabilidad asociado y eliminará también los documentos vinculados.
                    No se puede deshacer.
                </p>

                <div class="alert alert-danger py-2 mb-0">
                    <div class="fw-semibold mb-1">Se eliminará:</div>
                    <ul class="mb-0 ps-3 fs-13">
                        <li>La SOLMAT seleccionada.</li>
                        <li><span id="forceDestroySolcomCount">0</span> SOLCOM vinculada(s).</li>
                        <li><span id="forceDestroyOcCount">0</span> OC vinculada(s).</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmForceDestroySolmat">
                    <i class="ri-delete-bin-2-line me-1"></i>Confirmar eliminación total
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    var modalEl = document.getElementById('modalForceDestroySolmat');
    if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) return;

    var modal = new window.bootstrap.Modal(modalEl);
    var activeForm = null;

    var folioEl = document.getElementById('forceDestroySolmatFolio');
    var solcomCountEl = document.getElementById('forceDestroySolcomCount');
    var ocCountEl = document.getElementById('forceDestroyOcCount');
    var confirmBtn = document.getElementById('btnConfirmForceDestroySolmat');

    document.querySelectorAll('.js-solmat-force-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            activeForm = form;

            var folio = form.getAttribute('data-solmat-folio') || '—';
            var solcomCount = form.getAttribute('data-solcom-count') || '0';
            var ocCount = form.getAttribute('data-oc-count') || '0';

            if (folioEl) folioEl.textContent = '#' + folio;
            if (solcomCountEl) solcomCountEl.textContent = solcomCount;
            if (ocCountEl) ocCountEl.textContent = ocCount;

            modal.show();
        });
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (!activeForm) return;
            activeForm.submit();
        });
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
        activeForm = null;
    });
}());
</script>
@endpush
