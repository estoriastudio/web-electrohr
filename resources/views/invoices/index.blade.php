@extends('layouts.app')

@section('page_title', 'Facturas')

@section('breadcrumbs')
	<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
	<li class="breadcrumb-item active">Facturas</li>
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

<div class="card">
	<div class="card-header d-flex justify-content-between align-items-center border-bottom">
		<h4 class="card-title mb-0"><i class="ri-file-list-3-line me-1"></i> Facturas de Órdenes de Compra</h4>
		<div class="d-flex align-items-center gap-2">
			<span class="badge bg-secondary-subtle text-secondary">{{ $invoices->total() }} registro(s)</span>
			<div class="dropdown">
				<button class="btn btn-success btn-sm dropdown-toggle" type="button" id="invoiceExportDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
					<i class="ri-file-excel-2-line me-1"></i>Exportar a Excel
				</button>
				<div class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="invoiceExportDropdown" style="min-width: 320px;">
					@php
						$exportPaymentConditions = old('payment_conditions', ['credito', 'contado']);
					@endphp
					<form method="GET" action="{{ route('invoices.export') }}" class="row g-2" id="invoiceExportForm">
						<div class="col-6">
							<label for="export_start_date" class="form-label mb-1">Fecha inicio</label>
							<input type="date" id="export_start_date" name="start_date" value="{{ old('start_date') }}" class="form-control form-control-sm" required>
						</div>
						<div class="col-6">
							<label for="export_end_date" class="form-label mb-1">Fecha final</label>
							<input type="date" id="export_end_date" name="end_date" value="{{ old('end_date') }}" class="form-control form-control-sm" required>
						</div>
						<div class="col-12">
							<span class="form-label d-block mb-1">Tipos incluidos</span>
							<div class="d-flex gap-3">
								<div class="form-check">
									<input class="form-check-input" type="checkbox" name="payment_conditions[]" value="credito" id="export_payment_condition_credito" @checked(in_array('credito', $exportPaymentConditions, true))>
									<label class="form-check-label" for="export_payment_condition_credito">Crédito</label>
								</div>
								<div class="form-check">
									<input class="form-check-input" type="checkbox" name="payment_conditions[]" value="contado" id="export_payment_condition_contado" @checked(in_array('contado', $exportPaymentConditions, true))>
									<label class="form-check-label" for="export_payment_condition_contado">Contado</label>
								</div>
							</div>
						</div>
						<div class="col-12 d-grid mt-2">
							<button type="submit" class="btn btn-success" id="invoiceExportSubmit">
								<span class="js-export-default"><i class="ri-download-2-line me-1"></i>Descargar Excel</span>
								<span class="js-export-loading d-none"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Preparando Documento (<span class="js-export-countdown">10</span> s)</span>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<div class="card-body border-bottom py-3">
		<form method="GET" action="{{ route('invoices.index') }}" class="row g-2 align-items-end">
			<div class="col-12">
				<label class="form-label form-label-sm mb-2">Bandeja</label>
				<ul class="nav nav-tabs nav-justified" role="tablist" aria-label="Bandeja de Facturas">
					<li class="nav-item" role="presentation">
						<a href="{{ route('invoices.index', ['section' => 'en_proceso', 'payment_condition' => $paymentCondition !== 'todas' ? $paymentCondition : null, 'search' => $search ?: null]) }}"
						   class="nav-link {{ ($section ?? 'en_proceso') === 'en_proceso' ? 'active' : '' }}">
							<i class="ri-time-line me-1"></i>
							Pendientes
							<span class="badge rounded-pill bg-warning-subtle text-warning ms-1">{{ $pendingCount ?? 0 }}</span>
						</a>
					</li>
					<li class="nav-item" role="presentation">
						<a href="{{ route('invoices.index', ['section' => 'aceptada', 'payment_condition' => $paymentCondition !== 'todas' ? $paymentCondition : null, 'search' => $search ?: null]) }}"
						   class="nav-link {{ ($section ?? '') === 'aceptada' ? 'active' : '' }}">
							<i class="ri-checkbox-circle-line me-1"></i>
							Aprobadas
							<span class="badge rounded-pill bg-success-subtle text-success ms-1">{{ $acceptedCount ?? 0 }}</span>
						</a>
					</li>
					<li class="nav-item" role="presentation">
						<a href="{{ route('invoices.index', ['section' => 'rechazada', 'payment_condition' => $paymentCondition !== 'todas' ? $paymentCondition : null, 'search' => $search ?: null]) }}"
						   class="nav-link {{ ($section ?? '') === 'rechazada' ? 'active' : '' }}">
							<i class="ri-close-circle-line me-1"></i>
							Rechazadas
							<span class="badge rounded-pill bg-danger-subtle text-danger ms-1">{{ $rejectedCount ?? 0 }}</span>
						</a>
					</li>
					<li class="nav-item" role="presentation">
						<a href="{{ route('invoices.index', ['section' => 'todas', 'payment_condition' => $paymentCondition !== 'todas' ? $paymentCondition : null, 'search' => $search ?: null]) }}"
						   class="nav-link {{ ($section ?? '') === 'todas' ? 'active' : '' }}">
							<i class="ri-stack-line me-1"></i>
							Todas
							<span class="badge rounded-pill bg-secondary-subtle text-secondary ms-1">{{ ($pendingCount ?? 0) + ($acceptedCount ?? 0) + ($rejectedCount ?? 0) }}</span>
						</a>
					</li>
				</ul>
			</div>

			<input type="hidden" name="section" value="{{ $section ?? 'en_proceso' }}">
			<input type="hidden" name="payment_condition" value="{{ $paymentCondition }}">

			<div class="col-md-9">
				<div class="input-group input-group-sm">
					<span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
					<input type="text" name="search" value="{{ $search }}" class="form-control"
						   placeholder="Buscar por folio factura, OC, comprador o proveedor..."
						   autocomplete="off">
				</div>
			</div>
			<div class="col-md-3 d-flex gap-1">
				<button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
				@if ($search)
					<a href="{{ route('invoices.index', ['section' => $section ?? 'en_proceso', 'payment_condition' => $paymentCondition !== 'todas' ? $paymentCondition : null]) }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
						<i class="ri-close-line"></i>
					</a>
				@endif
			</div>
		</form>
	</div>

	<div class="card-body p-0">
		<div class="table-responsive invoice-table-responsive">
			<table class="table align-middle table-hover table-centered mb-0 app-list-table">
				<thead class="bg-light-subtle">
					<tr>
						<th>Acciones</th>
						<th>Estatus</th>
						<th>Fecha carga</th>
						<th>Emisión</th>
						<th>Vencimiento</th>
						<th>Folio</th>
						<th>OC</th>
						<th>Tipo</th>
						<th>Proveedor</th>
						<th>Comprador</th>
						<th class="text-end">Alcance líquido</th>
						<th class="text-center">Aprobación</th>
					</tr>
				</thead>
				<tbody>
					@forelse($invoices as $invoice)
						@php
							$statusMap = [
								'en_proceso' => ['label' => 'En Proceso', 'class' => 'bg-warning-subtle text-warning'],
								'aceptada' => ['label' => 'Aceptada', 'class' => 'bg-success-subtle text-success'],
								'rechazada' => ['label' => 'Rechazada', 'class' => 'bg-danger-subtle text-danger'],
							];
							$statusMeta = $statusMap[$invoice->status] ?? $statusMap['en_proceso'];
							$po = $invoice->purchaseOrder;
							$supplierName = $po?->supplier?->commercial_name ?: $po?->supplier?->rfc_name;
							$paymentConditions = $po?->milestones
								->pluck('payment_condition')
								->filter()
								->unique()
								->values() ?? collect();
							$netScope = (float) ($invoice->net_scope ?? $invoice->amount ?? 0);
							$milestoneOptions = $po
								? $po->milestones->values()->map(function ($m, $index) use ($invoice) {
									$concept = $m->concept ?: ($m->payment_condition === 'contado' ? 'Contado' : 'Crédito');
									$paymentAmount = (float) $m->payments
										->reject(fn ($payment) => $payment->status === 'rechazado')
										->sum('amount');
									return [
										'id' => $m->id,
										'label' => 'Hito #' . ($index + 1) . ' - ' . $concept . ' . ' . $invoice->currency . ' ' . number_format($paymentAmount, 2),
										'amount' => $paymentAmount,
									];
								})->all()
								: [];
							$currentMilestoneIds = $invoice->milestones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
						@endphp
						<tr>
							<td>
								<div class="d-flex align-items-center gap-1">
									<a href="{{ route('invoices.show', $invoice) }}" title="Ver detalle">
										<i class="ri-eye-line me-2 text-muted"></i>
									</a>

									<div class="dropdown">
										<button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Acciones">
											<i class="ri-more-2-fill"></i>
										</button>
										<ul class="dropdown-menu dropdown-menu-end">
											@if ($invoice->file_path)
												<li>
													<a class="dropdown-item" href="{{ route('invoices.download', $invoice) }}" target="_blank">
														<i class="ri-file-pdf-2-line me-2 text-muted"></i>Descargar PDF
													</a>
												</li>
											@endif

											@if ($invoice->credit_note_file_path)
												<li>
													<a class="dropdown-item" href="{{ route('invoices.download_file', ['invoice' => $invoice, 'type' => 'credit_note_pdf']) }}" target="_blank">
														<i class="ri-file-warning-line me-2 text-muted"></i>Descargar nota de crédito
													</a>
												</li>
											@else
												<li>
													<span class="dropdown-item-text text-muted">
														<i class="ri-file-warning-line me-2"></i>Sin nota de crédito
													</span>
												</li>
											@endif
										</ul>
									</div>
								</div>
							</td>
							<td><span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span></td>
							<td>
								<i class="ri-calendar-line me-1 text-muted" aria-hidden="true"></i>{{ optional($invoice->attached_at ?? $invoice->created_at)->format('d/m/Y H:i') }}
							</td>
							<td>{{ optional($invoice->issue_date)->format('d/m/Y') ?: '—' }}</td>
							<td>{{ optional($invoice->due_date)->format('d/m/Y') ?: '—' }}</td>
							<td class="fw-medium">{{ $invoice->folio ?: ('FACT-' . $invoice->id) }}</td>
							<td>
								@if ($po)
									<a href="{{ route('purchase_orders.show', $po) }}" class="text-decoration-none" target="_blank">
										<span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12 font-monospace">#{{ $po->folio ?? $po->id }}</span>
									</a>
								@else
									—
								@endif
							</td>
							<td>
								@if ($paymentConditions->isNotEmpty())
									<div class="d-flex flex-wrap gap-1">
										@foreach ($paymentConditions as $condition)
											<span class="badge {{ $condition === 'contado' ? 'bg-info-subtle text-info' : 'bg-primary-subtle text-primary' }}">
												{{ $condition === 'contado' ? 'Contado' : 'Crédito' }}
											</span>
										@endforeach
									</div>
								@else
									—
								@endif
							</td>
							<td>{{ $supplierName ?: '—' }}</td>
							<td>{{ $po?->elaborated_by ?: '—' }}</td>
							<td class="text-end fw-semibold">{{ $invoice->currency }} {{ number_format($netScope, 2) }}</td>
							<td class="text-center">
								@if ($po)
									<button type="button"
											class="btn btn-warning btn-sm js-open-validation-modal"
											data-invoice-id="{{ $invoice->id }}"
											data-invoice-folio="{{ $invoice->folio ?: ('FACT-' . $invoice->id) }}"
											data-po-folio="{{ $po->folio ?: ('OC #' . $po->id) }}"
											data-invoice-amount="{{ number_format((float) $invoice->amount, 2, '.', '') }}"
											data-invoice-currency="{{ $invoice->currency }}"
											data-current-status="{{ $invoice->status }}"
											data-current-milestone-ids='@json($currentMilestoneIds)'
											data-milestones='@json($milestoneOptions)'
											title="Validar factura">
										<i class="ri-shield-check-line me-1"></i>Aprobación
									</button>
								@else
									<span class="text-muted">—</span>
								@endif
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="12" class="text-center text-muted py-4">No hay facturas registradas.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	@if($invoices->hasPages())
		<div class="card-footer d-flex justify-content-end">
			{{ $invoices->links('pagination::bootstrap-5') }}
		</div>
	@endif
</div>

<div class="modal fade" id="invoiceValidationModal" tabindex="-1" aria-labelledby="invoiceValidationModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="invoiceValidationModalLabel">Validar factura</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<form method="POST" id="invoiceValidationForm" data-base-action="{{ route('invoices.status.update', ['invoice' => '__INVOICE__']) }}">
				@csrf
				@method('PATCH')
				<div class="modal-body">
					<div class="alert alert-light border mb-3">
						<div class="d-flex justify-content-between flex-wrap gap-2">
							<div>
								<div class="text-muted fs-12">Factura</div>
								<div class="fw-semibold" id="modalInvoiceFolio">—</div>
							</div>
							<div>
								<div class="text-muted fs-12">Orden de compra</div>
								<div class="fw-semibold" id="modalPurchaseOrderFolio">—</div>
							</div>
						</div>
					</div>

					<div class="mb-3">
						<label class="form-label fw-medium">Estatus</label>
						<select name="status" id="modalStatusSelect" class="form-select" required>
							<option value="en_proceso">En Proceso</option>
							<option value="aceptada">Aceptada</option>
							<option value="rechazada">Rechazada</option>
						</select>
					</div>

					<div id="modalMilestonesBlock" class="border rounded p-3 bg-light d-none">
						<div class="fw-medium mb-2">Selecciona el hito relacionado</div>
						<div class="text-muted fs-12 mb-3">Obligatorio cuando el estatus sea Aceptada.</div>
						<div id="modalMilestonesList" class="row g-2">
							<div class="col-12 text-muted fs-13">No hay hitos para esta OC.</div>
						</div>
						<div class="border rounded p-3 bg-white mt-3" id="modalMilestoneSummary">
							<div class="d-flex justify-content-between align-items-center gap-2">
								<span class="text-muted fs-13">Total hitos seleccionados</span>
								<span class="fw-semibold" id="modalMilestoneSelectedTotal">—</span>
							</div>
							<div class="d-flex justify-content-between align-items-center gap-2 mt-2">
								<span class="text-muted fs-13">Importe factura</span>
								<span class="fw-semibold" id="modalMilestoneInvoiceAmount">—</span>
							</div>
							<div class="d-flex justify-content-between align-items-center gap-2 mt-2 pt-2 border-top">
								<span class="text-muted fs-13">Diferencia</span>
								<span class="fw-semibold" id="modalMilestoneDifference">—</span>
							</div>
							<div class="fs-12 mt-2" id="modalMilestoneMatchStatus"></div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary">Guardar validación</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endsection

@push('styles')
<style>
.invoice-table-responsive {
	overflow: visible;
}

.invoice-table-responsive .dropdown-menu {
	z-index: 1085;
}

.invoice-table-responsive .app-list-table {
	width: 100%;
	table-layout: fixed;
}

.invoice-table-responsive .app-list-table th,
.invoice-table-responsive .app-list-table td {
	white-space: normal;
	overflow-wrap: anywhere;
}

.invoice-table-responsive .app-list-table th:nth-child(1) { width: 6%; }
.invoice-table-responsive .app-list-table th:nth-child(2) { width: 7%; }
.invoice-table-responsive .app-list-table th:nth-child(3) { width: 8%; }
.invoice-table-responsive .app-list-table th:nth-child(4) { width: 7%; }
.invoice-table-responsive .app-list-table th:nth-child(5) { width: 8%; }
.invoice-table-responsive .app-list-table th:nth-child(6) { width: 9%; }
.invoice-table-responsive .app-list-table th:nth-child(7) { width: 6%; }
.invoice-table-responsive .app-list-table th:nth-child(8) { width: 8%; }
.invoice-table-responsive .app-list-table th:nth-child(9) { width: 12%; }
.invoice-table-responsive .app-list-table th:nth-child(10) { width: 9%; }
.invoice-table-responsive .app-list-table th:nth-child(11) { width: 10%; }
.invoice-table-responsive .app-list-table th:nth-child(12) { width: 10%; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
	const openButtons = document.querySelectorAll('.js-open-validation-modal');
	const modalEl = document.getElementById('invoiceValidationModal');
	const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
	const form = document.getElementById('invoiceValidationForm');
	const statusSelect = document.getElementById('modalStatusSelect');
	const invoiceFolioEl = document.getElementById('modalInvoiceFolio');
	const poFolioEl = document.getElementById('modalPurchaseOrderFolio');
	const milestonesBlock = document.getElementById('modalMilestonesBlock');
	const milestonesList = document.getElementById('modalMilestonesList');
	const milestoneSelectedTotalEl = document.getElementById('modalMilestoneSelectedTotal');
	const milestoneInvoiceAmountEl = document.getElementById('modalMilestoneInvoiceAmount');
	const milestoneDifferenceEl = document.getElementById('modalMilestoneDifference');
	const milestoneMatchStatusEl = document.getElementById('modalMilestoneMatchStatus');
	const exportForm = document.getElementById('invoiceExportForm');
	const exportSubmit = document.getElementById('invoiceExportSubmit');

	function syncMilestoneBlock() {
		if (!statusSelect || !milestonesBlock) return;
		const requiresMilestone = statusSelect.value === 'aceptada';
		milestonesBlock.classList.toggle('d-none', !requiresMilestone);

		if (milestonesList) {
			milestonesList.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
				checkbox.required = false;
			});
		}
	}

	function formatMilestoneAmount(amount) {
		const currency = form?.dataset.invoiceCurrency || '';
		const formattedAmount = new Intl.NumberFormat('es-MX', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		}).format(amount);

		return [currency, formattedAmount].filter(Boolean).join(' ');
	}

	function syncMilestoneSummary() {
		if (!form || !milestoneSelectedTotalEl || !milestoneInvoiceAmountEl || !milestoneDifferenceEl || !milestoneMatchStatusEl) return;

		const selectedTotal = Array.from(form.querySelectorAll('input[name="milestone_ids[]"]:checked'))
			.reduce(function (total, checkbox) {
				return total + (Number(checkbox.dataset.amount) || 0);
			}, 0);
		const invoiceAmount = Number(form.dataset.invoiceAmount) || 0;
		const difference = selectedTotal - invoiceAmount;
		const matchesInvoice = Math.abs(difference) < 0.01;

		milestoneSelectedTotalEl.textContent = formatMilestoneAmount(selectedTotal);
		milestoneInvoiceAmountEl.textContent = formatMilestoneAmount(invoiceAmount);
		milestoneDifferenceEl.textContent = `${difference > 0 ? '+' : difference < 0 ? '-' : ''}${formatMilestoneAmount(Math.abs(difference))}`;
		milestoneDifferenceEl.classList.toggle('text-success', matchesInvoice);
		milestoneDifferenceEl.classList.toggle('text-warning', !matchesInvoice);
		milestoneMatchStatusEl.textContent = matchesInvoice
			? 'El importe de los hitos coincide con la factura.'
			: 'El importe de los hitos no coincide con la factura.';
		milestoneMatchStatusEl.className = `fs-12 mt-2 ${matchesInvoice ? 'text-success' : 'text-warning'}`;
	}

	function renderMilestones(milestones, currentMilestoneIds, invoiceId) {
		if (!milestonesList) return;

		if (!milestones || !milestones.length) {
			milestonesList.innerHTML = '<div class="col-12 text-muted fs-13">No hay hitos para esta OC.</div>';
			return;
		}

		let html = '';
		milestones.forEach(function (milestone) {
			const checked = Array.isArray(currentMilestoneIds)
				&& currentMilestoneIds.map(String).includes(String(milestone.id))
				? 'checked'
				: '';
			html += `
				<div class="col-md-6">
					<div class="form-check border rounded p-2 bg-white">
						<input class="form-check-input" type="checkbox"
							name="milestone_ids[]"
							value="${milestone.id}"
							data-amount="${milestone.amount}"
							id="modal_milestone_${invoiceId}_${milestone.id}"
							${checked}>
						<label class="form-check-label fs-13" for="modal_milestone_${invoiceId}_${milestone.id}">
							${milestone.label}
						</label>
					</div>
				</div>
			`;
		});

		milestonesList.innerHTML = html;
	}

	openButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			if (!form || !statusSelect || !modal) return;

			const invoiceId = this.getAttribute('data-invoice-id') || '';
			const invoiceFolio = this.getAttribute('data-invoice-folio') || '—';
			const poFolio = this.getAttribute('data-po-folio') || '—';
			const invoiceAmount = this.getAttribute('data-invoice-amount') || '0';
			const invoiceCurrency = this.getAttribute('data-invoice-currency') || '';
			const currentStatus = this.getAttribute('data-current-status') || 'en_proceso';
			const currentMilestoneIdsJson = this.getAttribute('data-current-milestone-ids') || '[]';
			const milestonesJson = this.getAttribute('data-milestones') || '[]';

			let milestones = [];
			let currentMilestoneIds = [];
			try {
				milestones = JSON.parse(milestonesJson);
			} catch (e) {
				milestones = [];
			}
			try {
				currentMilestoneIds = JSON.parse(currentMilestoneIdsJson);
			} catch (e) {
				currentMilestoneIds = [];
			}

			const actionTemplate = form.getAttribute('data-base-action') || '';
			form.setAttribute('action', actionTemplate.replace('__INVOICE__', invoiceId));

			invoiceFolioEl.textContent = invoiceFolio;
			poFolioEl.textContent = poFolio;
			form.dataset.invoiceAmount = invoiceAmount;
			form.dataset.invoiceCurrency = invoiceCurrency;
			statusSelect.value = currentStatus;

			renderMilestones(milestones, currentMilestoneIds, invoiceId);
			syncMilestoneBlock();
			syncMilestoneSummary();

			modal.show();
		});
	});

	if (statusSelect) {
		statusSelect.addEventListener('change', syncMilestoneBlock);
	}

	if (milestonesList) {
		milestonesList.addEventListener('change', syncMilestoneSummary);
	}

	if (form) {
		form.addEventListener('submit', function (e) {
			if (!statusSelect || statusSelect.value !== 'aceptada') return;

			const checkedMilestones = form.querySelectorAll('input[name="milestone_ids[]"]:checked');
			if (!checkedMilestones.length) {
				e.preventDefault();
				alert('Para aprobar una factura debes seleccionar al menos un hito relacionado.');
			}
		});
	}

	if (exportForm && exportSubmit) {
		function setExportButtonLoading(isLoading) {
			exportSubmit.disabled = isLoading;
			exportSubmit.querySelector('.js-export-default')?.classList.toggle('d-none', isLoading);
			exportSubmit.querySelector('.js-export-loading')?.classList.toggle('d-none', !isLoading);
		}

		exportForm.addEventListener('submit', function () {
			setExportButtonLoading(true);
			const countdown = exportSubmit.querySelector('.js-export-countdown');
			let secondsRemaining = 10;
			if (countdown) countdown.textContent = secondsRemaining;

			const countdownTimer = window.setInterval(function () {
				secondsRemaining -= 1;
				if (countdown) countdown.textContent = secondsRemaining;

				if (secondsRemaining > 0) return;

				window.clearInterval(countdownTimer);
				setExportButtonLoading(false);
			}, 1000);
		});
	}
});
</script>
@endpush

