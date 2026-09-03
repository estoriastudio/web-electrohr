@extends('layouts.app')
@section('page_title', 'Periodos de nómina')
@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li><li class="breadcrumb-item active">Nómina</li>@endsection
@section('content')
@include('human_resources.partials.flash')
<div class="row g-3">
	<div class="col-lg-4">
		<div class="card">
			<div class="card-header"><h5 class="card-title mb-0">Nuevo periodo</h5></div>
			<div class="card-body">
				<form method="POST" action="{{ route('human_resources.payroll-periods.store') }}">
					@csrf
					<label class="form-label">Viernes inicial</label>
					<input name="start_date" type="date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}" required>
					@error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
					<label class="form-label mt-3">Notas</label>
					<textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
					<button class="btn btn-primary w-100 mt-3">Crear periodo</button>
				</form>
			</div>
		</div>
	</div>
	<div class="col-lg-8">
		<div class="card">
			<div class="card-header">
				<h5 class="card-title mb-1">Periodos</h5>
				<p class="text-muted fs-12 mb-0">Ver líneas abre el detalle por trabajador; Generar crea las líneas para el personal activo; Cerrar bloquea cambios para preparar el pago.</p>
			</div>
			<div class="">
				<table class="table table-hover align-middle mb-0">
					<thead class="bg-light-subtle"><tr><th class="text-center"></th><th>Semana</th><th>Rango</th><th>Líneas</th><th>Estatus</th><th class="text-end">Acciones</th></tr></thead>
					<tbody>
						@forelse($payrollPeriods as $period)
							@php($canEditPeriod = $period->status === 'open' && $period->lines_count === 0)
							<tr>
								<td class="text-center">
									@if($canEditPeriod)
										<div class="dropdown">
											<button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Más acciones para el periodo {{ $period->week_number }}/{{ $period->year }}"><i class="ri-more-2-fill"></i></button>
											<ul class="dropdown-menu">
												<li><button type="button" class="dropdown-item" data-edit-payroll-period data-bs-toggle="modal" data-bs-target="#editPayrollPeriodModal" data-action="{{ route('human_resources.payroll-periods.update', $period) }}" data-start-date="{{ $period->start_date->format('Y-m-d') }}" data-notes="{{ $period->notes }}"><i class="ri-edit-line me-2"></i>Editar</button></li>
												<li><hr class="dropdown-divider"></li>
												<li><form method="POST" action="{{ route('human_resources.payroll-periods.destroy', $period) }}" onsubmit="return confirm('¿Eliminar este periodo de nómina?')">@csrf @method('DELETE')<button type="submit" class="dropdown-item text-danger"><i class="ri-delete-bin-line me-2"></i>Eliminar</button></form></li>
											</ul>
										</div>
									@else
										<button type="button" class="btn btn-light btn-sm" title="No se puede editar ni eliminar un periodo con líneas o no abierto" disabled><i class="ri-more-2-fill"></i></button>
									@endif
								</td>
								<td class="fw-medium">{{ $period->week_number }}/{{ $period->year }}</td>
								<td>{{ $period->start_date->format('d/m/Y') }} - {{ $period->end_date->format('d/m/Y') }}</td>
								<td>{{ $period->lines_count }}</td>
								<td><span class="badge {{ $period->status === 'open' ? 'bg-success-subtle text-success' : ($period->status === 'closed' ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary') }}">{{ ['open' => 'Abierto', 'closed' => 'Cerrado', 'paid' => 'Pagado'][$period->status] }}</span></td>
								<td class="text-end">
									<a class="btn btn-light btn-sm" href="{{ route('human_resources.payroll-lines.index', ['payroll_period_id' => $period->id]) }}" title="Ver líneas"><i class="ri-list-check-2 me-1"></i>Ver líneas</a>
									@if($period->status === 'open')
										<form class="d-inline" method="POST" action="{{ route('human_resources.payroll-periods.generate-lines', $period) }}">@csrf<button class="btn btn-light btn-sm" title="Generar líneas"><i class="ri-play-line me-1"></i>Generar</button></form>
										<form class="d-inline" method="POST" action="{{ route('human_resources.payroll-periods.close', $period) }}">@csrf<button class="btn btn-light btn-sm text-warning" title="Cerrar periodo"><i class="ri-lock-line me-1"></i>Cerrar</button></form>
									@elseif($period->status === 'closed')
										<form class="d-inline" method="POST" action="{{ route('human_resources.payroll-periods.mark-paid', $period) }}">@csrf<button class="btn btn-light btn-sm text-success" title="Marcar como pagado"><i class="ri-checkbox-circle-line me-1"></i>Marcar pagado</button></form>
									@endif
								</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center text-muted py-4">No hay periodos de nómina.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@if($payrollPeriods->hasPages())<div class="card-footer">{{ $payrollPeriods->links('pagination::bootstrap-5') }}</div>@endif
		</div>
	</div>
</div>

<div class="modal fade" id="editPayrollPeriodModal" tabindex="-1" aria-labelledby="editPayrollPeriodModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<form id="editPayrollPeriodForm" method="POST" class="modal-content">
			@csrf
			@method('PUT')
			<div class="modal-header"><h5 class="modal-title" id="editPayrollPeriodModalLabel">Editar periodo de nómina</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
			<div class="modal-body"><div class="mb-3"><label class="form-label" for="editPayrollPeriodStartDate">Viernes inicial</label><input id="editPayrollPeriodStartDate" name="start_date" type="date" class="form-control" required></div><div><label class="form-label" for="editPayrollPeriodNotes">Notas</label><textarea id="editPayrollPeriodNotes" name="notes" class="form-control" rows="3"></textarea></div></div>
			<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
		</form>
	</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
	const form = document.getElementById('editPayrollPeriodForm');

	document.querySelectorAll('[data-edit-payroll-period]').forEach(function (button) {
		button.addEventListener('click', function () {
			form.action = button.dataset.action;
			form.querySelector('[name="start_date"]').value = button.dataset.startDate;
			form.querySelector('[name="notes"]').value = button.dataset.notes;
		});
	});
});
</script>
@endpush