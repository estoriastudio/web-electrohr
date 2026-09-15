@extends('layouts.app')
@section('page_title', 'Salidas de inventario')
@section('content')
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
	{{ session('success') }}
	<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
	{{ session('error') }}
	<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show" role="alert">
	<strong>No fue posible registrar la salida.</strong>
	<ul class="mb-0 mt-1">
		@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
	</ul>
	<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
<div class="card">
	<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
		<div><h4 class="card-title mb-0"><i class="ri-arrow-right-circle-line text-primary me-1"></i>Salidas de inventario</h4><span class="text-muted fs-13">Entregas definitivas y préstamos de herramienta</span></div>
		<div class="d-flex gap-2"><a href="{{ route('stocks.entries.index') }}" class="btn btn-sm btn-outline-primary"><i class="ri-arrow-left-line me-1"></i>Ir a entradas</a><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#exitModal"><i class="ri-add-line me-1"></i>Nueva salida</button></div>
	</div>
	<div class="card-body border-bottom py-3">
		<form method="GET" action="{{ route('stocks.exits.index') }}" class="row g-2 align-items-end">
			<div class="col-md-5"><label class="form-label fs-12 mb-1">Concepto</label><div class="input-group input-group-sm"><span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span><input name="search" value="{{ $search }}" class="form-control" placeholder="Código o descripción" autocomplete="off"></div></div>
			<div class="col-md-2"><label class="form-label fs-12 mb-1">Desde</label><input name="date_from" type="date" value="{{ $dateFrom }}" class="form-control form-control-sm"></div>
			<div class="col-md-2"><label class="form-label fs-12 mb-1">Hasta</label><input name="date_to" type="date" value="{{ $dateTo }}" class="form-control form-control-sm"></div>
			<div class="col-md-3 d-flex gap-1"><button class="btn btn-primary btn-sm flex-fill"><i class="ri-filter-3-line me-1"></i>Filtrar</button>@if($search || $dateFrom || $dateTo)<a href="{{ route('stocks.exits.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros"><i class="ri-close-line"></i></a>@endif</div>
		</form>
	</div>
	<div class="card-body p-0"><div class="table-responsive"><table class="table align-middle table-hover table-centered mb-0"><thead class="bg-light-subtle"><tr><th>Fecha</th><th>Vale</th><th>Concepto / Herramienta</th><th>Receptor</th><th>Tipo</th><th>Estado</th></tr></thead><tbody>@forelse($exits as $exit)@php($isLoan = $exit->exit_type === 'tool_loan')@php($statusMap = ['completed' => ['label' => 'Completada', 'class' => 'bg-success-subtle text-success', 'icon' => 'ri-check-line'], 'open' => ['label' => 'Pendiente de retorno', 'class' => 'bg-warning-subtle text-warning', 'icon' => 'ri-time-line'], 'returned' => ['label' => 'Devuelta', 'class' => 'bg-info-subtle text-info', 'icon' => 'ri-arrow-go-back-line']])@php($status = $statusMap[$exit->status] ?? ['label' => $exit->status, 'class' => 'bg-secondary-subtle text-secondary', 'icon' => 'ri-question-line'])<tr><td class="text-nowrap"><i class="ri-calendar-line text-muted me-1"></i>{{ $exit->exited_at->format('d/m/Y') }}</td><td class="fw-semibold">{{ $exit->voucher_number }}</td><td><span class="fw-semibold d-block">{{ $exit->concept?->code ?? $exit->tool?->economic_number }}</span><span class="text-muted fs-12">{{ $exit->concept?->description ?? ($exit->tool?->name ?: $exit->tool?->description) }}</span></td><td>{{ $exit->recipientWorker ? trim($exit->recipientWorker->first_name . ' ' . $exit->recipientWorker->last_name) : ($exit->recipient_name ?: '—') }}</td><td><span class="badge {{ $isLoan ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary' }} py-1 px-2 fs-12"><i class="{{ $isLoan ? 'ri-tools-line' : 'ri-arrow-right-line' }} me-1"></i>{{ $isLoan ? 'Préstamo' : 'Salida definitiva' }}</span></td><td><span class="badge {{ $status['class'] }} py-1 px-2 fs-12"><i class="{{ $status['icon'] }} me-1"></i>{{ $status['label'] }}</span>@if($isLoan && $exit->expected_return_at)<small class="d-block text-muted fs-11 mt-1">Retorno: {{ $exit->expected_return_at->format('d/m/Y') }}</small>@endif</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4"><i class="ri-arrow-right-circle-line fs-24 d-block mb-1 opacity-50"></i>No hay salidas con los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div>
	@if($exits->hasPages())<div class="card-footer d-flex justify-content-end">{{ $exits->links('pagination::bootstrap-5') }}</div>@endif
</div>
<div class="modal fade" id="exitModal" tabindex="-1" aria-labelledby="exitModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<form class="modal-content" action="{{ route('stocks.exits.store') }}" method="POST">
			@csrf
			<input type="hidden" name="exit_type" id="exit_type" value="">
			<div class="modal-header"><h5 class="modal-title" id="exitModalLabel"><i class="ri-arrow-right-circle-line me-1"></i>Nueva salida</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
			<div class="modal-body">
				<div id="exitTypePicker">
					<p class="text-muted mb-3">Seleccione el tipo de movimiento para continuar.</p>
					<div class="row g-3">
						<div class="col-md-6"><button type="button" class="btn btn-outline-primary w-100 py-4 exit-type-choice" data-exit-type="definitive"><i class="ri-arrow-right-line fs-24 d-block mb-2"></i>Salida definitiva</button></div>
						<div class="col-md-6"><button type="button" class="btn btn-outline-warning w-100 py-4 exit-type-choice" data-exit-type="tool_loan"><i class="ri-tools-line fs-24 d-block mb-2"></i>Préstamo de herramienta</button></div>
					</div>
				</div>
				<div id="exitDetails" class="d-none">
					<div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3"><h6 class="mb-0" id="exitTypeTitle"></h6><button type="button" class="btn btn-light btn-sm" id="changeExitType"><i class="ri-arrow-left-line me-1"></i>Cambiar tipo</button></div>
					<div class="alert alert-light border py-2 fs-13">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</div>
					<div id="definitiveFields" class="d-none">
						<h6 class="text-uppercase fs-12 text-muted mb-3">Material entregado</h6>
						<div class="row g-3">
							<div class="col-md-6"><label for="concept_code" class="form-label">Suministro <span class="text-danger">*</span></label><select id="concept_code" name="concept_code" class="form-select"><option value="">Escriba al menos 3 caracteres</option></select><div class="form-text">Busque por código o descripción. El stock disponible se valida al registrar.</div></div>
							<div class="col-md-3"><label for="definitive_quantity" class="form-label">Cantidad <span class="text-danger">*</span></label><input id="definitive_quantity" name="quantity" type="number" step="0.001" min="0.001" class="form-control"></div>
							<div class="col-md-3"><label for="definitive_exited_at" class="form-label">Fecha de salida <span class="text-danger">*</span></label><input id="definitive_exited_at" name="exited_at" type="date" value="{{ now()->toDateString() }}" class="form-control"></div>
							<div class="col-md-6"><label for="definitive_voucher_number" class="form-label">Número de vale <span class="text-danger">*</span></label><input id="definitive_voucher_number" name="voucher_number" class="form-control"></div>
							<div class="col-md-6"><label for="recipient_name" class="form-label">Entregado a <span class="text-danger">*</span></label><input id="recipient_name" name="recipient_name" class="form-control"></div>
						</div>
					</div>
					<div id="loanFields" class="d-none">
						<h6 class="text-uppercase fs-12 text-muted mb-3">Datos del préstamo</h6>
						<input type="hidden" name="quantity" value="1">
						<div class="row g-3">
							<div class="col-md-6"><label for="tool_id" class="form-label">Herramienta <span class="text-danger">*</span></label><select id="tool_id" name="tool_id" class="form-select"><option value="">Seleccione una herramienta</option>@foreach($tools as $tool)<option value="{{ $tool->id }}">{{ $tool->economic_number }} - {{ $tool->name ?: $tool->description }}</option>@endforeach</select></div>
							<div class="col-md-6"><label for="recipient_worker_id" class="form-label">Trabajador responsable <span class="text-danger">*</span></label><select id="recipient_worker_id" name="recipient_worker_id" class="form-select"><option value="">Seleccione un trabajador</option>@foreach($workers as $worker)<option value="{{ $worker->id }}">{{ $worker->first_name }} {{ $worker->last_name }}</option>@endforeach</select></div>
							<div class="col-md-4"><label for="loan_voucher_number" class="form-label">Número de vale <span class="text-danger">*</span></label><input id="loan_voucher_number" name="voucher_number" class="form-control"></div>
							<div class="col-md-4"><label for="project_id" class="form-label">Proyecto <span class="text-danger">*</span></label><select id="project_id" name="project_id" class="form-select"><option value="">Escriba al menos 3 caracteres</option></select></div>
							<div class="col-md-4"><label for="project_work_id" class="form-label">Obra <span class="text-danger">*</span></label><select id="project_work_id" name="project_work_id" class="form-select"><option value="">Seleccione primero un proyecto</option></select></div>
							<div class="col-md-6"><label for="loan_exited_at" class="form-label">Fecha de entrega <span class="text-danger">*</span></label><input id="loan_exited_at" name="exited_at" type="date" value="{{ now()->toDateString() }}" class="form-control"></div>
							<div class="col-md-6"><label for="expected_return_at" class="form-label">Fecha tentativa de retorno <span class="text-danger">*</span></label><input id="expected_return_at" name="expected_return_at" type="date" class="form-control"></div>
						</div>
					</div>
					<div class="border-top pt-3 mt-4"><label for="observations" class="form-label">Observaciones</label><textarea id="observations" name="observations" class="form-control" rows="2" maxlength="2000"></textarea></div>
				</div>
			</div>
			<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary d-none" id="saveExit"><i class="ri-save-line me-1"></i>Registrar salida</button></div>
		</form>
	</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
	var modal = document.getElementById('exitModal');
	var typePicker = document.getElementById('exitTypePicker');
	var details = document.getElementById('exitDetails');
	var typeInput = document.getElementById('exit_type');
	var definitiveFields = document.getElementById('definitiveFields');
	var loanFields = document.getElementById('loanFields');
	var saveButton = document.getElementById('saveExit');
	var title = document.getElementById('exitTypeTitle');
	var definitiveInputs = definitiveFields.querySelectorAll('input, select');
	var loanInputs = loanFields.querySelectorAll('input, select');
	var projectSelect = document.getElementById('project_id');
	var projectWorkSelect = document.getElementById('project_work_id');
	var conceptChoices = null;
	var projectChoices = null;
	var projectWorkChoices = null;

	function enableSection(inputs, enabled) {
		inputs.forEach(function (input) {
			if (input.tagName !== 'SELECT') input.disabled = !enabled;
			input.required = enabled;
		});
	}
	function initializeRemoteSearch(select, urlBuilder, placeholder, labelBuilder, valueBuilder) {
		var choices = new Choices(select, {
			searchEnabled: true, searchFloor: 3, searchPlaceholderValue: placeholder,
			itemSelectText: '', noResultsText: 'Sin resultados', noChoicesText: 'Escriba para buscar', shouldSort: false
		});
		var searchInput = choices.containerOuter.element.querySelector('.choices__input--cloned');
		var debounceTimer = null;
		searchInput.addEventListener('input', function () {
			var query = this.value.trim();
			clearTimeout(debounceTimer);
			if (query.length < 3) return;
			debounceTimer = setTimeout(function () {
				fetch(urlBuilder(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
					.then(function (response) { return response.json(); })
					.then(function (items) {
						choices.clearChoices();
						choices.setChoices(items.map(function (item) { return { value: valueBuilder(item), label: labelBuilder(item) }; }), 'value', 'label', true);
					});
			}, 250);
		});
		return choices;
	}
	function loadProjectWorks() {
		if (projectWorkChoices) { projectWorkChoices.destroy(); projectWorkChoices = null; }
		projectWorkSelect.innerHTML = '<option value="">Escriba al menos 3 caracteres</option>';
		if (!projectSelect.value) return;
		projectWorkChoices = initializeRemoteSearch(
			projectWorkSelect,
			function (query) { return '/proyectos/' + projectSelect.value + '/obras/buscar?q=' + encodeURIComponent(query); },
			'Buscar obra...',
			function (work) { return work.name; },
			function (work) { return work.id; }
		);
	}
	function chooseType(type) {
		var isLoan = type === 'tool_loan';
		typeInput.value = type;
		typePicker.classList.add('d-none'); details.classList.remove('d-none');
		definitiveFields.classList.toggle('d-none', isLoan); loanFields.classList.toggle('d-none', !isLoan);
		saveButton.classList.remove('d-none'); title.textContent = isLoan ? 'Préstamo de herramienta' : 'Salida definitiva';
		enableSection(definitiveInputs, !isLoan); enableSection(loanInputs, isLoan);
		if (isLoan) document.getElementById('loan_exited_at').required = true;
	}
	document.querySelectorAll('.exit-type-choice').forEach(function (button) { button.addEventListener('click', function () { chooseType(this.dataset.exitType); }); });
	document.getElementById('changeExitType').addEventListener('click', function () { typeInput.value = ''; details.classList.add('d-none'); typePicker.classList.remove('d-none'); saveButton.classList.add('d-none'); enableSection(definitiveInputs, false); enableSection(loanInputs, false); });
	projectSelect.addEventListener('change', loadProjectWorks);
	modal.addEventListener('show.bs.modal', function () { typeInput.value = ''; details.classList.add('d-none'); typePicker.classList.remove('d-none'); saveButton.classList.add('d-none'); enableSection(definitiveInputs, false); enableSection(loanInputs, false); });
	modal.addEventListener('shown.bs.modal', function () {
		if (!conceptChoices) conceptChoices = initializeRemoteSearch(document.getElementById('concept_code'), function (query) { return @json(route('concepts.search')) + '?q=' + encodeURIComponent(query) + '&limit=30'; }, 'Buscar código o descripción...', function (concept) { return concept.code + ' - ' + concept.description; }, function (concept) { return concept.code; });
		if (!projectChoices) projectChoices = initializeRemoteSearch(projectSelect, function (query) { return @json(route('projects.search')) + '?q=' + encodeURIComponent(query); }, 'Buscar proyecto...', function (project) { return project.name; }, function (project) { return project.id; });
	});
	modal.addEventListener('hidden.bs.modal', function () {
		if (conceptChoices) { conceptChoices.destroy(); conceptChoices = null; }
		if (projectChoices) { projectChoices.destroy(); projectChoices = null; }
		if (projectWorkChoices) { projectWorkChoices.destroy(); projectWorkChoices = null; }
		projectWorkSelect.innerHTML = '<option value="">Seleccione primero un proyecto</option>';
	});
	@if($errors->any())
	new bootstrap.Modal(modal).show();
	@endif
});
</script>
@endpush
