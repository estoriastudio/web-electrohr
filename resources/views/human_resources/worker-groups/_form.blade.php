@php($workerGroup = $workerGroup ?? null)
@php($selectedProjectId = old('project_id', $workerGroup?->projectWork?->project_id))
@php($selectedProjectWorkId = old('project_work_id', $workerGroup?->project_work_id))
@php($availableWorks = $selectedProjectId ? $projectWorks->where('project_id', $selectedProjectId) : collect())

<div class="row g-3 worker-group-form">
	<div class="col-md-6">
		<label class="form-label" for="name">Nombre</label>
		<input id="name" class="form-control" name="name" value="{{ old('name', $workerGroup?->name) }}" required>
	</div>
	<div class="col-md-6">
		<label class="form-label" for="workerGroupProjectId">Proyecto <span class="text-danger">*</span></label>
		<select id="workerGroupProjectId" class="form-select" name="project_id" required>
			<option value="">Selecciona un proyecto</option>
			@foreach($projects as $project)
				<option value="{{ $project->id }}" @selected((string) $selectedProjectId === (string) $project->id)>{{ $project->name }}</option>
			@endforeach
		</select>
	</div>
	<div class="col-md-6">
		<label class="form-label" for="workerGroupProjectWorkId">Obra <span class="text-danger">*</span></label>
		<select id="workerGroupProjectWorkId" class="form-select" name="project_work_id" required @disabled(! $selectedProjectId)>
			<option value="">Selecciona una obra</option>
			@foreach($availableWorks as $projectWork)
				<option value="{{ $projectWork->id }}" @selected((string) $selectedProjectWorkId === (string) $projectWork->id)>{{ $projectWork->name }}</option>
			@endforeach
		</select>
	</div>
	<div class="col-md-3">
		<label class="form-label" for="status">Estatus</label>
		<select id="status" class="form-select" name="status" required>
			<option value="active" @selected(old('status', $workerGroup?->status ?? 'active') === 'active')>Activa</option>
			<option value="inactive" @selected(old('status', $workerGroup?->status) === 'inactive')>Inactiva</option>
		</select>
	</div>
	<div class="col-12">
		<label class="form-label" for="notes">Notas</label>
		<textarea id="notes" class="form-control" name="notes" rows="3">{{ old('notes', $workerGroup?->notes) }}</textarea>
	</div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.worker-group-form').forEach(function (form) {
		var projectSelect = form.querySelector('#workerGroupProjectId');
		var workSelect = form.querySelector('#workerGroupProjectWorkId');
		var modal = form.closest('.modal');
		var projectChoices = null;
		var workChoices = null;

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

		if (modal) {
			modal.addEventListener('shown.bs.modal', initializeChoices);
			modal.addEventListener('hidden.bs.modal', function () {
				if (projectChoices) {
					projectChoices.destroy();
					projectChoices = null;
				}
				destroyWorkChoices();
			});
		} else {
			initializeChoices();
		}
	});
});
</script>
@endpush