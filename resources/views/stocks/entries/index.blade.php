@extends('layouts.app')
@section('page_title', 'Entradas de inventario')
@section('content')
@if(session('success'))<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if(session('error'))<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if($errors->any())<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>No fue posible registrar la entrada.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div class="card">
	<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
		<div><h4 class="card-title mb-0"><i class="ri-inbox-line text-primary me-1"></i>Entradas de inventario</h4><span class="text-muted fs-13">Recepciones por compra y retornos de herramienta</span></div>
		<div class="d-flex gap-2"><a href="{{ route('stocks.exits.index') }}" class="btn btn-sm btn-outline-primary"><i class="ri-arrow-right-line me-1"></i>Ir a salidas</a><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#entryModal"><i class="ri-add-line me-1"></i>Nueva entrada</button></div>
	</div>
	<div class="card-body border-bottom py-3">
		<form method="GET" action="{{ route('stocks.entries.index') }}" class="row g-2 align-items-end">
			<div class="col-md-5"><label class="form-label fs-12 mb-1">Concepto</label><div class="input-group input-group-sm"><span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span><input name="search" value="{{ $search }}" class="form-control" placeholder="Código o descripción" autocomplete="off"></div></div>
			<div class="col-md-2"><label class="form-label fs-12 mb-1">Desde</label><input name="date_from" type="date" value="{{ $dateFrom }}" class="form-control form-control-sm"></div>
			<div class="col-md-2"><label class="form-label fs-12 mb-1">Hasta</label><input name="date_to" type="date" value="{{ $dateTo }}" class="form-control form-control-sm"></div>
			<div class="col-md-3 d-flex gap-1"><button class="btn btn-primary btn-sm flex-fill"><i class="ri-filter-3-line me-1"></i>Filtrar</button>@if($search || $dateFrom || $dateTo)<a href="{{ route('stocks.entries.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros"><i class="ri-close-line"></i></a>@endif</div>
		</form>
	</div>
	<div class="card-body p-0"><div class="table-responsive"><table class="table align-middle table-hover table-centered mb-0"><thead class="bg-light-subtle"><tr><th>Fecha</th><th>Concepto / Herramienta</th><th>Tipo de entrada</th><th class="text-end">Cantidad</th><th>Referencia</th><th>Documentos</th></tr></thead><tbody>@forelse($entries as $entry)@php($isPurchase = $entry->entry_type === 'purchase')<tr><td class="text-nowrap"><i class="ri-calendar-line text-muted me-1"></i>{{ $entry->received_at->format('d/m/Y') }}</td><td>@if($isPurchase)@forelse($entry->items as $item)<span class="fw-semibold d-block">{{ $item->concept->code }}</span><span class="text-muted fs-12 d-block">{{ $item->concept->description }}</span>@empty<span class="text-muted">—</span>@endforelse
@else<span class="fw-semibold d-block">{{ $entry->tool?->economic_number }}</span><span class="text-muted fs-12">{{ $entry->tool?->name ?: $entry->tool?->description }}</span>@endif</td><td><span class="badge {{ $isPurchase ? 'bg-success-subtle text-success' : 'bg-info-subtle text-info' }} py-1 px-2 fs-12"><i class="{{ $isPurchase ? 'ri-shopping-bag-3-line' : 'ri-tools-line' }} me-1"></i>{{ $isPurchase ? 'Compra' : 'Retorno de herramienta' }}</span></td><td class="text-end fw-semibold">@if($isPurchase)@foreach($entry->items as $item)<span class="d-block">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</span>@endforeach
@else{{ rtrim(rtrim(number_format((float) $entry->quantity, 3, '.', ''), '0'), '.') }}@endif</td><td class="fs-13">{{ $entry->purchase_reference ?: '—' }}</td><td>@if($entry->invoice_file_path || $entry->certificates->isNotEmpty())<span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12"><i class="ri-file-check-line me-1"></i>{{ ($entry->invoice_file_path ? 1 : 0) + $entry->certificates->count() }}</span>@else<span class="text-muted">—</span>@endif</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4"><i class="ri-inbox-line fs-24 d-block mb-1 opacity-50"></i>No hay entradas con los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div>
	@if($entries->hasPages())<div class="card-footer d-flex justify-content-end">{{ $entries->links('pagination::bootstrap-5') }}</div>@endif
</div>
<div class="modal fade" id="entryModal" tabindex="-1" aria-labelledby="entryModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<form class="modal-content" action="{{ route('stocks.entries.store') }}" method="POST" enctype="multipart/form-data">
			@csrf
			<input type="hidden" name="entry_type" id="entry_type" value="">
			<div class="modal-header">
				<h5 class="modal-title" id="entryModalLabel"><i class="ri-inbox-line me-1"></i>Nueva entrada</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<div id="entryTypePicker">
					<p class="text-muted mb-3">Seleccione el tipo de movimiento para continuar.</p>
					<div class="row g-3">
						<div class="col-md-6"><button type="button" class="btn btn-outline-primary w-100 py-4 entry-type-choice" data-entry-type="tool_return"><i class="ri-tools-line fs-24 d-block mb-2"></i>Retorno</button></div>
						<div class="col-md-6"><button type="button" class="btn btn-outline-success w-100 py-4 entry-type-choice" data-entry-type="purchase"><i class="ri-shopping-bag-3-line fs-24 d-block mb-2"></i>Compra</button></div>
					</div>
				</div>

				<div id="entryDetails" class="d-none">
					<div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
						<h6 class="mb-0" id="entryTypeTitle"></h6>
						<button type="button" class="btn btn-light btn-sm" id="changeEntryType"><i class="ri-arrow-left-line me-1"></i>Cambiar tipo</button>
					</div>
					<div class="alert alert-light border py-2 fs-13">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</div>

					<div id="purchaseFields" class="d-none">
						<h6 class="text-uppercase fs-12 text-muted mb-3">Datos de la compra</h6>
						<div class="row g-3"><div class="col-md-4"><label for="purchase_received_at" class="form-label">Fecha de entrada <span class="text-danger">*</span></label><input id="purchase_received_at" name="received_at" type="date" value="{{ now()->toDateString() }}" class="form-control"></div><div class="col-md-4"><label for="purchase_reference" class="form-label">Número SOLCOM u OC</label><input id="purchase_reference" name="purchase_reference" class="form-control"></div><div class="col-md-4"><label for="invoice" class="form-label">Factura PDF <span class="text-danger">*</span></label><input id="invoice" name="invoice" type="file" accept="application/pdf" class="form-control"></div></div>
						<div id="stockPurchaseItems" class="mt-4" data-stock-items="purchase"><div class="d-flex justify-content-between align-items-center mb-2"><h6 class="text-uppercase fs-12 text-muted mb-0">Suministros <span class="text-danger">*</span></h6><span class="badge bg-primary-subtle text-primary" data-items-count>0 conceptos</span></div><div class="table-responsive border"><table class="table table-sm align-middle mb-0"><thead class="bg-light-subtle"><tr><th>Código</th><th>Descripción</th><th>Unidad</th><th class="text-end">Cantidad</th><th>Certificados</th><th></th></tr></thead><tbody data-items-body><tr data-empty-row><td colspan="6" class="text-center text-muted py-3">Agregue al menos un suministro.</td></tr></tbody></table></div><div class="border border-top-0 p-3"><div data-search-state><label class="form-label fs-12 mb-1">Agregar suministro</label><div class="position-relative"><div class="input-group"><span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span><input type="search" class="form-control" data-concept-search placeholder="Buscar por código o descripción" autocomplete="off"></div><ul class="list-group position-absolute w-100 shadow d-none" data-concept-results style="z-index:1060;max-height:220px;overflow-y:auto"></ul></div></div><div class="d-none" data-selected-state><div class="row g-2 align-items-end"><div class="col-md-7"><span class="fw-semibold d-block" data-selected-code></span><span class="text-muted fs-12" data-selected-description></span></div><div class="col-md-3"><label class="form-label fs-12 mb-1">Cantidad</label><input type="number" min="0.001" step="0.001" class="form-control" data-selected-quantity></div><div class="col-md-2 d-flex gap-1"><button type="button" class="btn btn-light" data-change-concept title="Cambiar"><i class="ri-arrow-left-line"></i></button><button type="button" class="btn btn-primary flex-fill" data-add-item>Agregar</button></div></div><div class="row g-2 mt-1"><div class="col-md-6"><label class="form-label fs-12 mb-1">Certificado de origen</label><input type="file" accept="application/pdf" class="form-control form-control-sm" data-origin-certificate></div><div class="col-md-6"><label class="form-label fs-12 mb-1">Certificado de seguridad</label><input type="file" accept="application/pdf" class="form-control form-control-sm" data-safety-certificate></div></div></div><div class="text-danger fs-12 mt-2 d-none" data-items-error></div></div></div>
					</div>

					<div id="returnFields" class="d-none">
						<h6 class="text-uppercase fs-12 text-muted mb-3">Herramienta retornada</h6>
						<div class="row g-3">
							<div class="col-md-6"><label for="tool_id" class="form-label">Herramienta <span class="text-danger">*</span></label><select id="tool_id" name="tool_id" class="form-select"><option value="">Seleccione una herramienta</option>@foreach($tools as $tool)<option value="{{ $tool->id }}">{{ $tool->economic_number }} - {{ $tool->name ?: $tool->description }}</option>@endforeach</select></div>
							<div class="col-md-3"><label for="return_quantity" class="form-label">Cantidad <span class="text-danger">*</span></label><input id="return_quantity" name="quantity" type="number" step="0.001" min="0.001" value="1" class="form-control"></div>
							<div class="col-md-3"><label for="return_received_at" class="form-label">Fecha de retorno <span class="text-danger">*</span></label><input id="return_received_at" name="received_at" type="date" value="{{ now()->toDateString() }}" class="form-control"></div>
						</div>
					</div>

					<div class="border-top pt-3 mt-4"><label for="observations" class="form-label">Observaciones</label><textarea id="observations" name="observations" class="form-control" rows="2" maxlength="2000"></textarea></div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
				<button type="submit" class="btn btn-primary d-none" id="saveEntry"><i class="ri-save-line me-1"></i>Registrar entrada</button>
			</div>
		</form>
	</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
	var modal = document.getElementById('entryModal');
	var typePicker = document.getElementById('entryTypePicker');
	var details = document.getElementById('entryDetails');
	var typeInput = document.getElementById('entry_type');
	var purchaseFields = document.getElementById('purchaseFields');
	var returnFields = document.getElementById('returnFields');
	var saveButton = document.getElementById('saveEntry');
	var title = document.getElementById('entryTypeTitle');
	var purchaseInputs = purchaseFields.querySelectorAll('input');
	var requiredPurchaseInputs = [
		document.getElementById('purchase_received_at'),
		document.getElementById('invoice')
	];
	var returnInputs = returnFields.querySelectorAll('input, select');

	function setRequired(inputs, required) {
		inputs.forEach(function (input) { input.required = required; input.disabled = !required; });
	}

	function chooseType(type) {
		var isPurchase = type === 'purchase';
		typeInput.value = type;
		typePicker.classList.add('d-none');
		details.classList.remove('d-none');
		purchaseFields.classList.toggle('d-none', !isPurchase);
		returnFields.classList.toggle('d-none', isPurchase);
		saveButton.classList.remove('d-none');
		title.textContent = isPurchase ? 'Entrada por compra' : 'Retorno de herramienta';
		purchaseInputs.forEach(function (input) {
			input.required = false;
			input.disabled = !isPurchase;
		});
		requiredPurchaseInputs.forEach(function (input) { input.required = isPurchase; });
		setRequired(returnInputs, !isPurchase);
		document.getElementById('purchase_reference').disabled = !isPurchase;
		document.getElementById('invoice').required = isPurchase;
	}

	document.querySelectorAll('.entry-type-choice').forEach(function (button) {
		button.addEventListener('click', function () { chooseType(this.dataset.entryType); });
	});
	document.getElementById('changeEntryType').addEventListener('click', function () {
		typeInput.value = '';
		details.classList.add('d-none');
		typePicker.classList.remove('d-none');
		saveButton.classList.add('d-none');
		setRequired(purchaseInputs, false);
		setRequired(returnInputs, false);
	});
	modal.addEventListener('show.bs.modal', function () {
		typeInput.value = '';
		details.classList.add('d-none');
		typePicker.classList.remove('d-none');
		saveButton.classList.add('d-none');
		setRequired(purchaseInputs, false);
		setRequired(returnInputs, false);
	});
});
</script>
<script>
window.stockPurchaseItemsConfig = { searchUrl: @json(route('concepts.search')) + '?type=materiales&limit=30' };
</script>
<script src="{{ asset('assets/js/stock_movement_items.js') }}"></script>
@endpush
