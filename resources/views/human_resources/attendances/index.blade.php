@extends('layouts.app')

@section('page_title', 'Asistencias')

@section('breadcrumbs')
	<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
	<li class="breadcrumb-item active">Asistencias</li>
@endsection

@section('content')
@include('human_resources.partials.flash')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
	<div>
		<h4 class="mb-1">Asistencia diaria</h4>
		<span class="text-muted">Resumen por cuadrilla</span>
		<div class="dropdown mt-2">
			<button class="btn btn-success btn-sm dropdown-toggle" type="button" id="attendanceExportDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
				<i class="ri-file-excel-2-line me-1"></i>Exportar a Excel
			</button>
			<div class="dropdown-menu p-3" aria-labelledby="attendanceExportDropdown" style="min-width: 280px;">
				<form action="{{ route('human_resources.worker-attendances.export') }}" method="GET">
					<div class="mb-2">
						<label for="attendanceExportStartDate" class="form-label mb-1">Fecha inicio</label>
						<input type="date" id="attendanceExportStartDate" name="start_date" value="{{ old('start_date', $date) }}" class="form-control form-control-sm" required>
					</div>
					<div class="mb-3">
						<label for="attendanceExportEndDate" class="form-label mb-1">Fecha fin</label>
						<input type="date" id="attendanceExportEndDate" name="end_date" value="{{ old('end_date', $date) }}" class="form-control form-control-sm" required>
					</div>
					<button type="submit" class="btn btn-success btn-sm w-100"><i class="ri-download-2-line me-1"></i>Descargar reporte</button>
				</form>
			</div>
		</div>
	</div>
	<div class="d-flex align-items-center gap-2">
		<a href="{{ route('human_resources.worker-attendances.index', ['date' => $previousDate]) }}" class="btn btn-light" title="Día anterior"><i class="ri-arrow-left-s-line"></i></a>
		<div class="text-center px-2">
			<div class="fw-semibold">{{ $selectedDate->isToday() ? 'Hoy' : $selectedDate->translatedFormat('l') }}</div>
			<div class="text-muted fs-12">{{ $selectedDate->translatedFormat('d \d\e F \d\e Y') }}</div>
		</div>
		@if($canGoNext)
			<a href="{{ route('human_resources.worker-attendances.index', ['date' => $nextDate]) }}" class="btn btn-light" title="Día siguiente"><i class="ri-arrow-right-s-line"></i></a>
		@else
			<button type="button" class="btn btn-light" title="No hay registros futuros" disabled><i class="ri-arrow-right-s-line"></i></button>
		@endif
	</div>
</div>

<div class="row g-3 mb-3">
	<div class="col-sm-6 col-xl"><div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Cuadrillas</span><h3 class="mb-0 mt-1">{{ $summary['groups'] }}</h3></div></div></div>
	<div class="col-sm-6 col-xl"><div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Personal asignado</span><h3 class="mb-0 mt-1">{{ $summary['assigned'] }}</h3></div></div></div>
	<div class="col-sm-6 col-xl"><div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Show</span><h3 class="mb-0 mt-1 text-success">{{ $summary['present'] }}</h3></div></div></div>
	<div class="col-sm-6 col-xl"><div class="card h-100"><div class="card-body"><span class="text-muted fs-12">No show</span><h3 class="mb-0 mt-1 text-danger">{{ $summary['absent'] }}</h3></div></div></div>
	<div class="col-sm-6 col-xl"><div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Incapacidades</span><h3 class="mb-0 mt-1 text-warning">{{ $summary['incapacities'] }}</h3></div></div></div>
	<div class="col-sm-6 col-xl"><div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Pendientes</span><h3 class="mb-0 mt-1 text-warning">{{ $summary['pending'] }}</h3><span class="text-muted fs-12">Cobertura: {{ $summary['attendance_rate'] }}%</span></div></div></div>
</div>

<div class="card">
	<div class="card-header d-flex justify-content-between align-items-center border-bottom"><h5 class="card-title mb-0">Registros por cuadrilla</h5><span class="badge bg-light text-dark border">{{ $selectedDate->format('d/m/Y') }}</span></div>
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead class="bg-light-subtle"><tr><th>Cuadrilla</th><th>Obra</th><th class="text-center">Asignados</th><th class="text-center">Show</th><th class="text-center">No show</th><th class="text-center">Incapacidades</th><th class="text-center">Pendientes</th><th class="text-end">Acción</th></tr></thead>
			<tbody>
				@forelse($workerGroups as $workerGroup)
					<tr>
						<td class="fw-medium">{{ $workerGroup->name }}</td>
						<td>{{ $workerGroup->projectWork?->name ?: '—' }}</td>
						<td class="text-center">{{ $workerGroup->assigned_count }}</td>
						<td class="text-center"><span class="badge bg-success-subtle text-success">{{ $workerGroup->present_count }}</span></td>
						<td class="text-center"><span class="badge bg-danger-subtle text-danger">{{ $workerGroup->absent_count }}</span></td>
						<td class="text-center"><span class="badge {{ $workerGroup->incapacity_count > 0 ? 'bg-warning-subtle text-warning' : 'bg-light-subtle text-muted' }}">{{ $workerGroup->incapacity_count }}</span></td>
						<td class="text-center"><span class="badge {{ $workerGroup->pending_count > 0 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}">{{ $workerGroup->pending_count }}</span></td>
						<td class="text-end"><a class="btn btn-primary btn-sm" href="{{ route('human_resources.worker-groups.attendance', [$workerGroup, 'date' => $date]) }}"><i class="ri-calendar-check-line me-1"></i>Tomar asistencia</a></td>
					</tr>
				@empty
					<tr><td colspan="8" class="text-center text-muted py-4">No hay cuadrillas registradas.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection