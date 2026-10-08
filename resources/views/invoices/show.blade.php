@extends('layouts.app')

@section('page_title', 'Detalle de Factura')

@section('breadcrumbs')
	<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
	<li class="breadcrumb-item"><a href="{{ route('invoices.index') }}">Facturas</a></li>
	<li class="breadcrumb-item active">Detalle</li>
@endsection

@section('content')
@php
	$statusMap = [
		'en_proceso' => ['label' => 'En Proceso', 'class' => 'bg-warning-subtle text-warning'],
		'aceptada' => ['label' => 'Aceptada', 'class' => 'bg-success-subtle text-success'],
		'rechazada' => ['label' => 'Rechazada', 'class' => 'bg-danger-subtle text-danger'],
	];
	$statusMeta = $statusMap[$invoice->status] ?? $statusMap['en_proceso'];
	$po = $invoice->purchaseOrder;
	$currentMilestoneIds = $invoice->milestones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
@endphp

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

<div class="row g-3">
	<div class="col-lg-8">
		<div class="card">
			<div class="card-header border-bottom d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">Factura {{ $invoice->folio ?: ('#' . $invoice->id) }}</h5>
				<span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
			</div>
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-6">
						<label class="form-label text-muted mb-1">Orden de compra</label>
						<div class="fw-medium">
							@if ($po)
								<a href="{{ route('purchase_orders.show', $po) }}" target="_blank" class="text-decoration-none">
									{{ $po->folio ?: ('OC #' . $po->id) }}
								</a>
							@else
								—
							@endif
						</div>
					</div>
					<div class="col-md-6">
						<label class="form-label text-muted mb-1">Proveedor</label>
						<div class="fw-medium">{{ $po?->supplier?->commercial_name ?: $po?->supplier?->rfc_name ?: '—' }}</div>
					</div>
					<div class="col-md-6">
						<label class="form-label text-muted mb-1">Comprador</label>
						<div class="fw-medium">{{ $po?->buyer?->name ?: ($po?->elaborated_by ?: '—') }}</div>
					</div>
					<div class="col-md-6">
						<label class="form-label text-muted mb-1">Fecha de carga</label>
						<div class="fw-medium">{{ optional($invoice->attached_at ?? $invoice->created_at)->format('d/m/Y H:i') }}</div>
					</div>
					<div class="col-md-6">
						<label class="form-label text-muted mb-1">Fecha de emisión</label>
						<div class="fw-medium">{{ optional($invoice->issue_date)->format('d/m/Y') ?: '—' }}</div>
					</div>
					<div class="col-md-6">
						<label class="form-label text-muted mb-1">Fecha de vencimiento</label>
						<div class="fw-medium">{{ optional($invoice->due_date)->format('d/m/Y') ?: '—' }}</div>
					</div>
					<div class="col-md-4">
						<label class="form-label text-muted mb-1">Importe factura</label>
						<div class="fw-medium">{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</div>
					</div>
					<div class="col-md-4">
						<label class="form-label text-muted mb-1">Importe nota crédito</label>
						<div class="fw-medium">
							{{ $invoice->credit_note_amount !== null ? ($invoice->currency . ' ' . number_format((float) $invoice->credit_note_amount, 2)) : '—' }}
						</div>
					</div>
					<div class="col-md-4">
						<label class="form-label text-muted mb-1">Alcance líquido</label>
						<div class="fw-bold text-primary">{{ $invoice->currency }} {{ number_format((float) ($invoice->net_scope ?? $invoice->amount), 2) }}</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-4">
		@can('invoices.approve')
		<div class="card">
			<div class="card-header border-bottom">
				<h6 class="mb-0">Validación de Compras</h6>
			</div>
			<div class="card-body">
				<form method="POST" action="{{ route('invoices.status.update', $invoice) }}" id="invoiceApprovalForm">
					@csrf
					@method('PATCH')

					<div class="mb-3">
						<label class="form-label fw-medium">Estatus</label>
						<select name="status" class="form-select" id="invoiceStatusSelect" required>
							<option value="en_proceso" {{ $invoice->status === 'en_proceso' ? 'selected' : '' }}>En Proceso</option>
							<option value="aceptada" {{ $invoice->status === 'aceptada' ? 'selected' : '' }}>Aceptada</option>
							<option value="rechazada" {{ $invoice->status === 'rechazada' ? 'selected' : '' }}>Rechazada</option>
						</select>
					</div>

					<div class="mb-3 d-none" id="invoiceMilestonesBlock">
						<label class="form-label fw-medium">Hitos relacionados (obligatorio si Aceptada)</label>
						<div class="border rounded p-3 bg-light">
							<div class="row g-2" id="invoiceMilestonesList">
								@forelse(($po?->milestones ?? collect())->values() as $index => $milestone)
									@php
										$concept = $milestone->concept ?: ($milestone->payment_condition === 'contado' ? 'Contado' : 'Crédito');
										$paymentAmount = (float) $milestone->payments
											->reject(fn ($payment) => $payment->status === 'rechazado')
											->sum('amount');
									@endphp
									<div class="col-md-12">
										<div class="form-check border rounded p-2 bg-white">
											<input class="form-check-input" type="checkbox"
												name="milestone_ids[]"
												value="{{ $milestone->id }}"
												data-amount="{{ number_format($paymentAmount, 2, '.', '') }}"
												id="show_milestone_{{ $milestone->id }}"
												{{ in_array((int) $milestone->id, $currentMilestoneIds, true) ? 'checked' : '' }}>
											<label class="form-check-label fs-13" for="show_milestone_{{ $milestone->id }}">
												Hito #{{ $index + 1 }} - {{ $concept }} . {{ $invoice->currency }} {{ number_format($paymentAmount, 2) }}
											</label>
										</div>
									</div>
								@empty
									<div class="col-12 text-muted fs-13">No hay hitos disponibles para esta orden de compra.</div>
								@endforelse
							</div>
						</div>
						<div class="border rounded p-3 bg-white mt-3" id="invoiceMilestoneSummary">
							<div class="d-flex justify-content-between align-items-center gap-2">
								<span class="text-muted fs-13">Pagos de los hitos seleccionados</span>
								<span class="fw-semibold" id="invoiceMilestoneSelectedTotal">—</span>
							</div>
							<div class="d-flex justify-content-between align-items-center gap-2 mt-2">
								<span class="text-muted fs-13">Otras facturas aceptadas en esos hitos</span>
								<span class="fw-semibold" id="invoiceMilestoneOtherTotal">—</span>
							</div>
							<div class="d-flex justify-content-between align-items-center gap-2 mt-2">
								<span class="text-muted fs-13">Importe de esta factura</span>
								<span class="fw-semibold" id="invoiceMilestoneInvoiceAmount">{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</span>
							</div>
							<div class="d-flex justify-content-between align-items-center gap-2 mt-2 pt-2 border-top">
								<span class="text-muted fs-13">Diferencia (facturado - pagos)</span>
								<span class="fw-semibold" id="invoiceMilestoneDifference">—</span>
							</div>
							<div class="fs-12 mt-2" id="invoiceMilestoneMatchStatus"></div>
						</div>
					</div>

					<button type="submit" class="btn btn-primary w-100">Guardar validación</button>
				</form>
			</div>
		</div>
	@endcan

		<div class="card mt-3">
			<div class="card-header border-bottom">
				<h6 class="mb-0">Archivos</h6>
			</div>
			<div class="card-body d-flex flex-column gap-2">
				@if ($invoice->file_path)
					<a href="{{ route('invoices.download', $invoice) }}" target="_blank" class="btn btn-soft-primary btn-sm">
						<i class="ri-file-pdf-line me-1"></i> Ver PDF de factura
					</a>
				@endif

				@if ($invoice->xml_file_path)
					<a href="{{ route('invoices.download_file', ['invoice' => $invoice, 'type' => 'xml']) }}" target="_blank" class="btn btn-soft-info btn-sm">
						<i class="ri-code-s-slash-line me-1"></i> Ver XML de factura
					</a>
				@endif

				@if ($invoice->credit_note_file_path)
					<a href="{{ route('invoices.download_file', ['invoice' => $invoice, 'type' => 'credit_note_pdf']) }}" target="_blank" class="btn btn-soft-warning btn-sm">
						<i class="ri-file-warning-line me-1"></i> Ver PDF nota de crédito
					</a>
				@endif

				@if ($invoice->credit_note_xml_file_path)
					<a href="{{ route('invoices.download_file', ['invoice' => $invoice, 'type' => 'credit_note_xml']) }}" target="_blank" class="btn btn-soft-warning btn-sm">
						<i class="ri-code-s-slash-line me-1"></i> Ver XML nota de crédito
					</a>
				@endif

				@if ($invoice->evidence_file_path)
					<a href="{{ route('invoices.download_file', ['invoice' => $invoice, 'type' => 'evidence']) }}" target="_blank" class="btn btn-soft-success btn-sm">
						<i class="ri-archive-line me-1"></i> Ver evidencia
					</a>
				@endif
			</div>
		</div>

		@if (auth()->user()->hasAnyRole(['admin', 'Pagos', 'Orden de compra']))
		<div class="card mt-3 border-danger-subtle">
			<div class="card-header border-bottom">
				<h6 class="mb-0 text-danger">Eliminar factura</h6>
			</div>
			<div class="card-body">
				<p class="text-muted fs-13">Se eliminan la factura y sus archivos. Esta acción no se puede deshacer.</p>
				<form action="{{ route('invoices.destroy', $invoice) }}" method="POST"
					onsubmit="return confirm('¿Eliminar la factura {{ $invoice->folio ?: ($invoice->file_name ?: '#' . $invoice->id) }}? Esta acción no se puede deshacer.')">
					@csrf
					@method('DELETE')
					<button type="submit" class="btn btn-outline-danger btn-sm w-100">
						<i class="ri-delete-bin-line me-1"></i> Eliminar factura
					</button>
				</form>
			</div>
		</div>
		@endif
	</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
	const form = document.getElementById('invoiceApprovalForm');
	const statusSelect = document.getElementById('invoiceStatusSelect');
	const milestonesBlock = document.getElementById('invoiceMilestonesBlock');
	const milestoneChecks = form ? form.querySelectorAll('input[name="milestone_ids[]"]') : [];
	const milestoneSelectedTotalEl = document.getElementById('invoiceMilestoneSelectedTotal');
	const milestoneOtherTotalEl = document.getElementById('invoiceMilestoneOtherTotal');
	const milestoneDifferenceEl = document.getElementById('invoiceMilestoneDifference');
	const milestoneMatchStatusEl = document.getElementById('invoiceMilestoneMatchStatus');
	const invoiceAmount = {{ number_format((float) $invoice->amount, 2, '.', '') }};
	const invoiceCurrency = @json($invoice->currency);
	const milestoneInvoices = @json($milestoneInvoices);
	const amountTolerance = 0.5;

	function syncMilestonesBlock() {
		if (!statusSelect || !milestonesBlock) return;
		const requiresMilestones = statusSelect.value === 'aceptada';
		milestonesBlock.classList.toggle('d-none', !requiresMilestones);

		if (milestoneChecks && milestoneChecks.length) {
			milestoneChecks.forEach(function (check) {
				check.required = false;
			});
		}
	}

	function formatMilestoneAmount(amount) {
		const formattedAmount = new Intl.NumberFormat('es-MX', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		}).format(amount);

		return `${invoiceCurrency} ${formattedAmount}`;
	}

	function syncMilestoneSummary() {
		if (!form || !milestoneSelectedTotalEl || !milestoneDifferenceEl || !milestoneMatchStatusEl) return;

		const selectedChecks = Array.from(form.querySelectorAll('input[name="milestone_ids[]"]:checked'));
		const selectedTotal = selectedChecks.reduce(function (total, checkbox) {
			return total + (Number(checkbox.dataset.amount) || 0);
		}, 0);
		const otherInvoices = {};
		selectedChecks.forEach(function (checkbox) {
			(milestoneInvoices[checkbox.value] || []).forEach(function (other) {
				otherInvoices[other.id] = other.amount;
			});
		});
		const otherTotal = Object.values(otherInvoices).reduce(function (total, amount) { return total + amount; }, 0);
		const difference = otherTotal + invoiceAmount - selectedTotal;
		const matchesInvoice = Math.abs(difference) <= amountTolerance;

		milestoneSelectedTotalEl.textContent = formatMilestoneAmount(selectedTotal);
		if (milestoneOtherTotalEl) milestoneOtherTotalEl.textContent = formatMilestoneAmount(otherTotal);
		milestoneDifferenceEl.textContent = `${difference > 0 ? '+' : difference < 0 ? '-' : ''}${formatMilestoneAmount(Math.abs(difference))}`;
		milestoneDifferenceEl.classList.toggle('text-success', matchesInvoice);
		milestoneDifferenceEl.classList.toggle('text-warning', !matchesInvoice);
		milestoneMatchStatusEl.textContent = matchesInvoice
			? 'Las facturas del hito coinciden con el importe de sus pagos.'
			: (difference > 0
				? 'Las facturas exceden el importe de los pagos del hito; el estado de cuenta lo marcara Por revisar.'
				: 'Aun falta facturar parte del importe de los pagos del hito.');
		milestoneMatchStatusEl.className = `fs-12 mt-2 ${matchesInvoice ? 'text-success' : 'text-warning'}`;
	}

	syncMilestonesBlock();
	syncMilestoneSummary();

	if (statusSelect) {
		statusSelect.addEventListener('change', syncMilestonesBlock);
	}

	if (form) {
		form.addEventListener('change', function (event) {
			if (event.target.matches('input[name="milestone_ids[]"]')) {
				syncMilestoneSummary();
			}
		});
	}

	if (form) {
		form.addEventListener('submit', function (e) {
			if (!statusSelect) return;
			if (statusSelect.value !== 'aceptada') return;

			const checkedMilestones = form.querySelectorAll('input[name="milestone_ids[]"]:checked');
			if (!checkedMilestones.length) {
				e.preventDefault();
				alert('Para aprobar la factura debes seleccionar al menos un hito.');
				const firstMilestone = form.querySelector('input[name="milestone_ids[]"]');
				if (firstMilestone) firstMilestone.focus();
			}
		});
	}
});
</script>
@endpush

