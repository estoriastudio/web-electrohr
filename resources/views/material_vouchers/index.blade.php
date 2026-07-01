@extends('layouts.app')

@section('page_title', 'Vales de Material')

@section('breadcrumbs')
	<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
	<li class="breadcrumb-item active">Vales de Material</li>
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

<div class="card">
	<div class="card-header d-flex justify-content-between align-items-center border-bottom">
		<h4 class="card-title mb-0"><i class="ri-receipt-line me-1"></i> Vales de Material</h4>
		@can('create')
		<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateVoucher">
			<i class="ri-add-line me-1"></i> Nuevo vale
		</button>
		@endcan
	</div>

	<div class="card-body border-bottom py-3">
		<form method="GET" action="{{ route('material_vouchers.index') }}" class="row g-2">
			<div class="col-lg-8">
				<div class="input-group input-group-sm">
					<span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
					<input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Buscar por folio o proveedor..." autocomplete="off">
				</div>
			</div>
			<div class="col-lg-3">
				<select name="status" class="form-select form-select-sm">
					<option value="">Todos los estatus</option>
					<option value="emitido" {{ $status === 'emitido' ? 'selected' : '' }}>Emitido</option>
					<option value="autorizado" {{ $status === 'autorizado' ? 'selected' : '' }}>Autorizado</option>
					<option value="completado" {{ $status === 'completado' ? 'selected' : '' }}>Completado</option>
					<option value="facturado" {{ $status === 'facturado' ? 'selected' : '' }}>Facturado</option>
					<option value="pagado" {{ $status === 'pagado' ? 'selected' : '' }}>Pagado</option>
				</select>
			</div>
			<div class="col-lg-1 d-grid">
				<button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
			</div>
		</form>
	</div>

	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table align-middle text-nowrap table-hover table-centered mb-0">
				<thead class="bg-light-subtle">
					<tr>
						<th>Folio</th>
						<th>Fecha</th>
						<th>Proveedor</th>
						<th>Proyecto / Obra</th>
						<th>Renglones</th>
						<th>Estatus</th>
						<th>Acciones</th>
					</tr>
				</thead>
				<tbody>
					@php
						$statusMap = [
							'emitido'    => ['label' => 'Emitido', 'class' => 'bg-warning-subtle text-warning'],
							'autorizado' => ['label' => 'Autorizado', 'class' => 'bg-info-subtle text-info'],
							'completado' => ['label' => 'Completado', 'class' => 'bg-primary-subtle text-primary'],
							'facturado'  => ['label' => 'Facturado', 'class' => 'bg-secondary-subtle text-secondary'],
							'pagado'     => ['label' => 'Pagado', 'class' => 'bg-success-subtle text-success'],
						];
					@endphp
					@forelse ($materialVouchers as $voucher)
						@php
							$s = $statusMap[$voucher->status] ?? ['label' => ucfirst($voucher->status), 'class' => 'bg-secondary-subtle text-secondary'];
						@endphp
						<tr>
							<td><a href="{{ route('material_vouchers.show', $voucher) }}" class="fw-semibold text-dark">{{ $voucher->folio }}</a></td>
							<td>{{ $voucher->voucher_date?->format('d/m/Y') }}</td>
							<td>{{ $voucher->supplier->commercial_name ?? $voucher->supplier->rfc_name ?? '—' }}</td>
							<td>
								<span class="d-block">{{ $voucher->project?->name ?? '—' }}</span>
								@if($voucher->projectWork)
									<small class="text-muted">{{ $voucher->projectWork->name }}</small>
								@endif
							</td>
							<td><span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $voucher->items_count }}</span></td>
							<td><span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span></td>
							<td>
								<div class="d-flex gap-1">
									<a href="{{ route('material_vouchers.show', $voucher) }}" class="btn btn-light btn-sm" title="Ver detalle"><i class="ri-eye-line"></i></a>
									@can('update')
									<a href="{{ route('material_vouchers.edit', $voucher) }}" class="btn btn-soft-primary btn-sm" title="Editar"><i class="ri-edit-line"></i></a>
									@endcan
								</div>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="7" class="text-center text-muted py-4">
								<i class="ri-receipt-line fs-24 d-block mb-1 opacity-50"></i>
								No hay vales registrados.
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	<div class="card-footer d-flex justify-content-end">
		{{ $materialVouchers->links('pagination::bootstrap-5') }}
	</div>
</div>

@can('create')
<div class="modal fade" id="modalCreateVoucher" tabindex="-1" aria-labelledby="modalCreateVoucherLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<form action="{{ route('material_vouchers.store') }}" method="POST">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title" id="modalCreateVoucherLabel"><i class="ri-receipt-line me-1"></i> Nuevo Vale de Material</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">Proveedor <span class="text-danger">*</span></label>
							<select id="mvSupplierId" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
								<option value="">Selecciona proveedor</option>
								@foreach($suppliers as $supplier)
									<option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
										{{ $supplier->commercial_name ?: $supplier->rfc_name }}
									</option>
								@endforeach
							</select>
							@error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>

						<div class="col-md-6">
							<label class="form-label">Fecha del vale <span class="text-danger">*</span></label>
							<input type="date" name="voucher_date" value="{{ old('voucher_date', now()->toDateString()) }}" class="form-control @error('voucher_date') is-invalid @enderror" required>
							@error('voucher_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>

						<input type="hidden" name="project_id" value="">
						<input type="hidden" name="project_work_id" value="">

						<div class="col-12">
							<label class="form-label">Observación inicial</label>
							<textarea name="initial_note" rows="3" class="form-control @error('initial_note') is-invalid @enderror" placeholder="Observaciones opcionales al crear...">{{ old('initial_note') }}</textarea>
							@error('initial_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Crear vale</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endcan

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
	const modalElement = document.getElementById('modalCreateVoucher');
	const supplierSelect = document.getElementById('mvSupplierId');
	let supplierChoices = null;

	function initSupplierChoices() {
		if (!supplierSelect || supplierChoices || typeof Choices === 'undefined') return;
		supplierChoices = new Choices(supplierSelect, {
			searchEnabled: true,
			searchPlaceholderValue: 'Buscar proveedor...',
			itemSelectText: '',
			noResultsText: 'Sin resultados',
			noChoicesText: 'Sin opciones disponibles',
		});
	}

	if (modalElement) {
		modalElement.addEventListener('shown.bs.modal', initSupplierChoices);
	}

	@if($errors->any())
	if (modalElement && window.bootstrap) {
		const modal = new bootstrap.Modal(modalElement);
		modal.show();
		initSupplierChoices();
	}
	@endif
});
</script>
@endpush
