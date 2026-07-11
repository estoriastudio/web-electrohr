@extends('layouts.app')

@section('page_title', 'Precios Adjudicados')

@section('breadcrumbs')
	<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
	<li class="breadcrumb-item"><a href="{{ route('concepts.index') }}">Conceptos</a></li>
	<li class="breadcrumb-item active">Precios adjudicados</li>
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
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center border-bottom">
				<div>
					<h4 class="card-title mb-0">Precios adjudicados de suministros</h4>
				</div>
				<a href="{{ route('concepts.index') }}" class="btn btn-sm btn-light">
					<i class="ri-arrow-left-line me-1"></i> Volver a conceptos
				</a>
			</div>

			<div class="card-body border-bottom py-3">
				<form method="GET" action="{{ route('concepts.awarded_prices') }}" class="row g-2 align-items-end">
					<div class="col-md-10">
						<label class="form-label mb-1">Suministro (concepto)</label>
						<div class="input-group input-group-sm">
							<span class="input-group-text bg-light">
								<i class="ri-search-line text-muted"></i>
							</span>
							<input type="text"
								   name="search"
								   value="{{ $search }}"
								   class="form-control"
								   placeholder="Buscar por nombre o código del suministro..."
								   autocomplete="off">
						</div>
					</div>
					<div class="col-md-2 d-flex gap-1">
						<button type="submit" class="btn btn-primary btn-sm flex-fill">Buscar</button>
						@if ($search !== '')
							<a href="{{ route('concepts.awarded_prices') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar búsqueda">
								<i class="ri-close-line"></i>
							</a>
						@endif
					</div>
				</form>
			</div>

			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table align-middle table-hover table-centered mb-0 text-nowrap">
						<thead class="bg-light-subtle">
							<tr>
								<th>Folio OC</th>
								<th>Fecha OC</th>
								<th>Proveedor</th>
								<th>Concepto</th>
								<th>Unidad</th>
								<th class="text-end">Precio Adjudicado</th>
							</tr>
						</thead>
						<tbody>
							@if ($search === '')
								<tr>
									<td colspan="6" class="text-center text-muted py-4">
										<i class="ri-search-line fs-24 d-block mb-1 opacity-50"></i>
										Escribe el nombre del suministro para ver su histórico de precios.
									</td>
								</tr>
							@else
								@forelse ($items as $item)
									<tr>
										<td>
											@if($item->purchaseOrder)
												<a href="{{ route('purchase_orders.show', $item->purchaseOrder) }}" class="fw-semibold">
													#{{ $item->purchaseOrder->folio ?? $item->purchaseOrder->id }}
												</a>
											@else
												<span class="text-muted">—</span>
											@endif
										</td>
										<td>
											@if($item->purchaseOrder?->created_at)
												{{ $item->purchaseOrder->created_at->format('d/m/Y H:i') }}
											@else
												<span class="text-muted">—</span>
											@endif
										</td>
										<td>
											{{ $item->purchaseOrder?->supplier?->rfc_name
												?? $item->purchaseOrder?->supplier?->commercial_name
												?? 'Sin proveedor' }}
										</td>
										<td class="text-wrap" style="max-width:420px;">
											@if ($item->concept?->code)
												<span class="badge bg-light text-dark border me-1">{{ $item->concept->code }}</span>
											@endif
											{{ $item->concept?->description ?? $item->description }}
										</td>
										<td>{{ $item->unit ?: '—' }}</td>
										<td class="text-end fw-semibold">${{ number_format((float) $item->unit_price, 2) }}</td>
									</tr>
								@empty
									<tr>
										<td colspan="6" class="text-center text-muted py-4">
											<i class="ri-file-search-line fs-24 d-block mb-1 opacity-50"></i>
											No se encontraron órdenes de compra para "{{ $search }}".
										</td>
									</tr>
								@endforelse
							@endif
						</tbody>
					</table>
				</div>
			</div>

			@if ($search !== '' && $items->hasPages())
				<div class="card-footer d-flex justify-content-end">
					{{ $items->links('pagination::bootstrap-5') }}
				</div>
			@endif
		</div>
	</div>
</div>

@endsection
