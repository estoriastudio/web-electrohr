@extends('layouts.app')

@section('page_title', 'Cuadrilla')

@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('human_resources.worker-groups.index') }}">Cuadrillas</a></li><li class="breadcrumb-item active">{{ $workerGroup->name }}</li>@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
	<div>
		<h4 class="mb-1">{{ $workerGroup->name }}</h4>
		<span class="text-muted">{{ $workerGroup->projectWork?->name }}</span>
	</div>
	<div class="d-flex gap-2">
		<a class="btn btn-outline-secondary" href="{{ route('human_resources.worker-groups.edit', $workerGroup) }}">Editar</a>
		<button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#relocateWorkerGroupModal"><i class="ri-map-pin-line me-1"></i> Reubicar</button>
		<a class="btn btn-primary" href="{{ route('human_resources.worker-groups.attendance', [$workerGroup, 'date' => today()->toDateString()]) }}"><i class="ri-calendar-check-line me-1"></i> Asistencia</a>
		@if($workerGroup->status === 'active')
			<a class="btn btn-primary" href="#workerGroupMemberPanel"><i class="ri-user-add-line me-1"></i> Agregar integrante</a>
		@endif
	</div>
</div>

<div class="row g-3">
	<div class="col-12">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">Integrantes activos</h5>
				<span id="workerGroupMembersCount" class="badge bg-primary-subtle text-primary">{{ $workerGroup->activeMembers->count() }}</span>
			</div>
			<div class="table-responsive">
				<table class="table align-middle mb-0">
					<thead class="bg-light-subtle"><tr><th>Trabajador</th><th>Puesto</th><th>Ingreso</th><th class="text-end">Acción</th></tr></thead>
					<tbody id="workerGroupMembersTbody">
						@forelse($workerGroup->activeMembers as $member)
							<tr class="worker-group-member-row" data-worker-id="{{ $member->id }}">
								<td><a href="{{ route('human_resources.workers.show', $member) }}" class="text-dark">{{ $member->first_name }} {{ $member->last_name }}</a></td>
								<td>{{ $member->job_title ?: '—' }}</td>
								<td>{{ $member->pivot->joined_at?->format('d/m/Y') }}</td>
								<td class="text-end">
									<form class="d-inline-flex gap-2" method="POST" action="{{ route('human_resources.worker-groups.members.remove', [$workerGroup, $member]) }}">
										@csrf
										@method('DELETE')
										<input type="date" name="left_at" value="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" required>
										<button class="btn btn-sm btn-outline-danger" title="Remover"><i class="ri-user-unfollow-line"></i></button>
									</form>
								</td>
							</tr>
						@empty
							<tr id="workerGroupMembersEmpty"><td colspan="4" class="text-center text-muted py-4">No hay integrantes activos.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>

			@if($workerGroup->status === 'active')
				<div class="border-top px-3 py-3" id="workerGroupMemberPanel">
					<div id="workerGroupMemberSearchState">
						<p class="text-muted fs-12 mb-2 fw-medium"><i class="ri-user-search-line me-1 text-primary"></i>Buscar trabajador disponible</p>
						<div class="position-relative">
							<div class="input-group input-group-lg">
								<span class="input-group-text bg-light border-end-0"><i class="ri-search-line text-muted"></i></span>
								<input type="search" id="workerGroupMemberSearch" class="form-control border-start-0 ps-0" placeholder="Buscar por nombre, apellido o número de cuenta..." autocomplete="off">
							</div>
							<ul id="workerGroupMemberResults" class="list-group position-absolute w-100 shadow d-none" style="top:100%;left:0;max-height:280px;overflow-y:auto;z-index:1050"></ul>
						</div>
					</div>

					<div id="workerGroupMemberSelectedState" class="d-none">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<span class="text-success fs-13 fw-medium"><i class="ri-checkbox-circle-line me-1"></i>Trabajador seleccionado</span>
							<button type="button" id="workerGroupMemberChange" class="btn btn-link btn-sm p-0 text-muted text-decoration-none"><i class="ri-close-line me-1"></i>Cambiar</button>
						</div>
						<div class="rounded-2 border bg-primary-subtle p-3 mb-3">
							<p class="fw-bold mb-1 fs-15" id="workerGroupMemberName"></p>
							<p class="mb-0 text-body-secondary fs-13" id="workerGroupMemberDetails"></p>
						</div>
						<div class="row g-2 align-items-end">
							<div class="col-md-6">
								<label class="form-label fw-medium" for="workerGroupMemberJoinedAt">Fecha de ingreso <span class="text-danger">*</span></label>
								<input id="workerGroupMemberJoinedAt" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
							</div>
							<div class="col-md-6 d-grid">
								<button type="button" id="workerGroupMemberAdd" class="btn btn-primary"><i class="ri-user-add-line me-1"></i>Asignar a cuadrilla</button>
							</div>
						</div>
					</div>
					<div id="workerGroupMemberError" class="text-danger fs-12 mt-2 d-none"></div>
				</div>
			@endif
		</div>
	</div>
</div>

@php($selectedProjectId = old('project_id', $workerGroup->projectWork?->project_id))
@php($selectedProjectWorkId = old('project_work_id', $workerGroup->project_work_id))
@php($availableRelocationWorks = $selectedProjectId ? $projectWorks->where('project_id', $selectedProjectId) : collect())
<div class="modal fade" id="relocateWorkerGroupModal" tabindex="-1" aria-labelledby="relocateWorkerGroupModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form method="POST" action="{{ route('human_resources.worker-groups.relocate', $workerGroup) }}">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title" id="relocateWorkerGroupModalLabel"><i class="ri-map-pin-line me-2 text-primary"></i>Reubicar cuadrilla</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<p class="text-muted fs-13 mb-3">Selecciona el proyecto y la obra a la que se moverá <strong>{{ $workerGroup->name }}</strong>.</p>
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label" for="relocateProjectId">Proyecto <span class="text-danger">*</span></label>
							<select id="relocateProjectId" class="form-select" name="project_id" required>
								<option value="">Selecciona un proyecto</option>
								@foreach($projects as $project)
									<option value="{{ $project->id }}" @selected((string) $selectedProjectId === (string) $project->id)>{{ $project->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-6">
							<label class="form-label" for="relocateProjectWorkId">Obra <span class="text-danger">*</span></label>
							<select id="relocateProjectWorkId" class="form-select" name="project_work_id" required @disabled(! $selectedProjectId)>
								<option value="">Selecciona una obra</option>
								@foreach($availableRelocationWorks as $projectWork)
									<option value="{{ $projectWork->id }}" @selected((string) $selectedProjectWorkId === (string) $projectWork->id)>{{ $projectWork->name }}</option>
								@endforeach
							</select>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button class="btn btn-primary" type="submit"><i class="ri-map-pin-line me-1"></i>Reubicar</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endsection

@push('scripts')
<script>
window.workerGroupMembersConfig = {
	searchUrl: '{{ route('human_resources.worker-groups.members.search', $workerGroup) }}',
	storeUrl: '{{ route('human_resources.worker-groups.members.add', $workerGroup) }}',
	workerShowBaseUrl: '{{ url('/human_resources/workers') }}',
	removeUrlBase: '{{ url('/human_resources/worker-groups/' . $workerGroup->id . '/members') }}',
	csrfToken: '{{ csrf_token() }}',
};
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
	var modal = document.getElementById('relocateWorkerGroupModal');
	var projectSelect = document.getElementById('relocateProjectId');
	var workSelect = document.getElementById('relocateProjectWorkId');
	var projectChoices = null;
	var workChoices = null;

	if (!modal || !projectSelect || !workSelect) {
		return;
	}

	function destroyWorkChoices() {
		if (workChoices) {
			workChoices.destroy();
			workChoices = null;
		}
	}

	function initializeWorkChoices() {
		if (typeof Choices === 'undefined' || workSelect.disabled || workChoices) {
			return;
		}

		workChoices = new Choices(workSelect, {
			searchEnabled: true,
			searchPlaceholderValue: 'Buscar obra...',
			itemSelectText: '',
			noResultsText: 'Sin resultados',
			noChoicesText: 'Sin obras disponibles',
			shouldSort: false,
		});
	}

	function initializeChoices() {
		if (typeof Choices !== 'undefined' && !projectChoices) {
			projectChoices = new Choices(projectSelect, {
				searchEnabled: true,
				searchPlaceholderValue: 'Buscar proyecto...',
				itemSelectText: '',
				noResultsText: 'Sin resultados',
				noChoicesText: 'Sin proyectos disponibles',
			});
		}

		initializeWorkChoices();
	}

	function loadWorks(projectId) {
		destroyWorkChoices();
		workSelect.disabled = true;
		workSelect.innerHTML = '<option value="">Cargando obras...</option>';

		fetch('{{ url('/proyectos') }}/' + encodeURIComponent(projectId) + '/obras-json', {
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
		.then(function (response) {
			if (!response.ok) {
				throw new Error('No fue posible cargar las obras.');
			}

			return response.json();
		})
		.then(function (works) {
			workSelect.innerHTML = '<option value="">Selecciona una obra</option>';
			works.forEach(function (work) {
				var option = document.createElement('option');
				option.value = work.id;
				option.textContent = work.name;
				workSelect.appendChild(option);
			});

			workSelect.disabled = works.length === 0;
			if (works.length === 0) {
				workSelect.innerHTML = '<option value="">El proyecto no tiene obras activas</option>';
			}

			initializeWorkChoices();
		})
		.catch(function () {
			workSelect.disabled = true;
			workSelect.innerHTML = '<option value="">No fue posible cargar las obras</option>';
		});
	}

	projectSelect.addEventListener('change', function () {
		if (!projectSelect.value) {
			destroyWorkChoices();
			workSelect.disabled = true;
			workSelect.innerHTML = '<option value="">Selecciona primero un proyecto</option>';
			return;
		}

		loadWorks(projectSelect.value);
	});

	modal.addEventListener('shown.bs.modal', initializeChoices);
	modal.addEventListener('hidden.bs.modal', function () {
		if (projectChoices) {
			projectChoices.destroy();
			projectChoices = null;
		}
		destroyWorkChoices();
	});

	@if ($errors->has('project_id') || $errors->has('project_work_id'))
		new bootstrap.Modal(modal).show();
	@endif
});
</script>
<script src="{{ asset('assets/js/worker_group_members.js') }}"></script>
@endpush