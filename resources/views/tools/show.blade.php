@extends('layouts.app')

@push('styles')
<style>
	.photo-dz-label { font-size: .75rem; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .4rem; }
	.photo-dz-zone {
		min-height: 160px;
		border: 2px dashed #ced4da;
		border-radius: .5rem;
		background: #f8f9fa;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-direction: column;
		cursor: pointer;
		transition: border-color .2s, background .2s;
		overflow: hidden;
		padding: 0;
	}
	.photo-dz-zone.dz-drag-hover { border-color: #0d6efd; background: #e9f0ff; }
	.photo-dz-zone .dz-message { text-align: center; padding: 1rem; pointer-events: none; }
	.photo-dz-zone .dz-message i { font-size: 2.5rem; color: #adb5bd; display: block; margin-bottom: .5rem; }
	.photo-dz-zone .dz-message span { font-size: .8rem; color: #adb5bd; }
	.photo-dz-zone .dz-preview { margin: 0 !important; width: 100% !important; }
	.photo-dz-zone .dz-image { width: 100% !important; height: 158px !important; border-radius: 0 !important; }
	.photo-dz-zone .dz-image img { width: 100%; height: 100%; object-fit: cover; }
	.photo-dz-zone .dz-details,
	.photo-dz-zone .dz-success-mark,
	.photo-dz-zone .dz-error-mark,
	.photo-dz-zone .dz-progress { display: none !important; }
	.photo-existing {
		position: relative;
		min-height: 160px;
		border-radius: .5rem;
		overflow: hidden;
		background: #000;
	}
	.photo-existing img { width: 100%; height: 160px; object-fit: cover; display: block; }
	.photo-existing-overlay {
		position: absolute;
		inset: 0;
		background: rgba(0,0,0,.45);
		display: flex;
		align-items: center;
		justify-content: center;
		gap: .5rem;
		opacity: 0;
		transition: opacity .2s;
	}
	.photo-existing:hover .photo-existing-overlay { opacity: 1; }
	.photo-dz-error { font-size: .75rem; color: #dc3545; margin-top: .25rem; min-height: 1rem; }
</style>
@endpush

@section('page_title', 'Detalle de Herramienta')

@section('breadcrumbs')
	<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
	<li class="breadcrumb-item"><a href="{{ route('tools.index') }}">Herramientas</a></li>
	<li class="breadcrumb-item active">{{ $tool->economic_number }}</li>
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

<div class="row g-3">
	<div class="col-xl-4">
		<div class="card">
			<div class="card-header border-bottom d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0"><i class="ri-hammer-line me-1 text-primary"></i> Ficha de Inventario</h5>
				<div class="d-flex gap-2">
					<button type="button" class="btn btn-soft-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditTool">
						<i class="ri-edit-line me-1"></i> Editar
					</button>
					<a href="{{ route('tools.index') }}" class="btn btn-light btn-sm">
						<i class="ri-arrow-left-line me-1"></i> Regresar
					</a>
				</div>
			</div>
			<div class="card-body">
				@php
					$selectedCategory = $tool->category;
					$rootCategory = $selectedCategory?->parent_id ? $selectedCategory->parent : $selectedCategory;
					$subcategory = $selectedCategory?->parent_id ? $selectedCategory : null;
				@endphp

				<div class="mb-3">
					<label class="form-label text-muted mb-1">No. Económico</label>
					<div class="fw-semibold fs-15">{{ $tool->economic_number }}</div>
				</div>

				<div class="mb-3">
					<label class="form-label text-muted mb-1">Descripción</label>
					<div class="fw-medium">{{ $tool->description }}</div>
				</div>

				<div class="mb-3">
					<label class="form-label text-muted mb-1">Categoría</label>
					<div class="fw-medium">{{ $rootCategory?->name ?? 'Sin categoría' }}</div>
					@if($subcategory)
						<div class="text-muted fs-12">{{ $subcategory->name }}</div>
					@endif
				</div>

				<div class="row g-2 mb-3">
					<div class="col-6">
						<label class="form-label text-muted mb-1">Marca</label>
						<div>{{ $tool->brand ?: 'Sin marca' }}</div>
					</div>
					<div class="col-6">
						<label class="form-label text-muted mb-1">Modelo</label>
						<div>{{ $tool->model ?: 'Sin modelo' }}</div>
					</div>
					<div class="col-12">
						<label class="form-label text-muted mb-1">No. Serie</label>
						<div>{{ $tool->serial_number ?: 'Sin serie' }}</div>
					</div>
				</div>

				<div class="mb-3">
					<label class="form-label text-muted mb-1">Calibración</label>
					<div>
						@if($tool->requires_calibration)
							<span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Requiere calibración</span>
						@else
							<span class="badge bg-light text-muted py-1 px-2 fs-12">No requerida</span>
						@endif
					</div>
				</div>

				<div>
					<label class="form-label text-muted mb-1">Estatus</label>
					<div>
						@if($tool->status === 'active')
							<span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activa</span>
						@elseif($tool->status === 'in_service')
							<span class="badge bg-info-subtle text-info py-1 px-2 fs-12">En Servicio</span>
						@else
							<span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Inactiva</span>
						@endif
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-8">
		<div class="card mb-3">
			<div class="card-header border-bottom">
				<h5 class="card-title mb-0"><i class="ri-image-line me-1 text-primary"></i> Fotografías</h5>
			</div>
			<div class="card-body">
				<div class="row g-3">
					@for ($slot = 1; $slot <= 3; $slot++)
						@php
							$photoUrl = $tool->photoUrl($slot);
							$uploadUrl = route('tools.photo.upload', [$tool, $slot]);
							$deleteUrl = route('tools.photo.delete', [$tool, $slot]);
						@endphp
						<div class="col-md-4">
							<p class="photo-dz-label mb-1">Foto {{ $slot }}</p>

							@if ($photoUrl)
								<div class="photo-existing" id="photo-preview-{{ $slot }}">
									<img src="{{ $photoUrl }}" alt="Foto {{ $slot }}">
									<div class="photo-existing-overlay">
										<a href="{{ $photoUrl }}" target="_blank" class="btn btn-sm btn-light" title="Ver en tamaño completo">
											<i class="ri-eye-line"></i>
										</a>
										<form action="{{ $deleteUrl }}" method="POST" onsubmit="return confirm('¿Eliminar esta fotografía?')">
											@csrf @method('DELETE')
											<button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
												<i class="ri-delete-bin-line"></i>
											</button>
										</form>
									</div>
								</div>
							@else
								<div class="photo-existing d-none" id="photo-preview-{{ $slot }}">
									<img src="" alt="Foto {{ $slot }}">
									<div class="photo-existing-overlay">
										<a href="#" target="_blank" class="btn btn-sm btn-light photo-view-btn" title="Ver en tamaño completo">
											<i class="ri-eye-line"></i>
										</a>
										<form action="{{ $deleteUrl }}" method="POST" onsubmit="return confirm('¿Eliminar esta fotografía?')">
											@csrf @method('DELETE')
											<button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
												<i class="ri-delete-bin-line"></i>
											</button>
										</form>
									</div>
								</div>
							@endif

							<div class="photo-dz-zone {{ $photoUrl ? 'd-none' : '' }}"
								id="dropzone-slot-{{ $slot }}"
								data-slot="{{ $slot }}"
								data-upload-url="{{ $uploadUrl }}"
								data-csrf="{{ csrf_token() }}">
								<div class="dz-message needsclick">
									<i class="ri-image-add-line"></i>
									<span>Arrastra o <strong>haz clic</strong><br>JPG, PNG, WEBP - max. 5 MB</span>
								</div>
							</div>
							<div class="photo-dz-error" id="dz-error-{{ $slot }}"></div>

							@if ($photoUrl)
								<button type="button" class="btn btn-sm btn-outline-secondary mt-2 w-100 btn-replace-photo" data-slot="{{ $slot }}" title="Reemplazar foto">
									<i class="ri-refresh-line me-1"></i> Reemplazar
								</button>
							@endif
						</div>
					@endfor
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header border-bottom d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0"><i class="ri-history-line me-1 text-primary"></i> Historial de Control de Uso</h5>
				<div class="d-flex gap-2">
					<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateControlFromDetail">
						<i class="ri-add-line me-1"></i> Nuevo registro
					</button>
					<a href="{{ route('tool_controls.index', ['search' => $tool->economic_number]) }}" class="btn btn-soft-primary btn-sm">
						<i class="ri-external-link-line me-1"></i> Ir a Control de uso
					</a>
				</div>
			</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table align-middle text-nowrap table-hover table-centered mb-0">
						<thead class="bg-light-subtle">
							<tr>
								<th>Proyecto</th>
								<th>Obra</th>
								<th>Responsable</th>
								<th>Préstamo</th>
								<th>Salida</th>
								<th>Revisión</th>
								<th>Estatus</th>
								<th>Observaciones</th>
							</tr>
						</thead>
						<tbody>
							@forelse($tool->controls as $control)
								@php
									$firstObservation = is_array($control->observations) && count($control->observations)
										? ($control->observations[0]['note'] ?? null)
										: null;
								@endphp
								<tr>
									<td>{{ $control->projectWork?->project?->name ?? 'Sin proyecto' }}</td>
									<td>{{ $control->projectWork?->name ?? 'Sin obra' }}</td>
									<td>{{ $control->responsible ?: '—' }}</td>
									<td>{{ $control->loan_type === 'fixed' ? 'Fijo' : 'Provisional' }}</td>
									<td>{{ $control->checkout_date?->format('Y-m-d') ?? '—' }}</td>
									<td>{{ $control->review_date?->format('Y-m-d') ?? '—' }}</td>
									<td>
										@if($control->status === 'active')
											<span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activo</span>
										@else
											<span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Cerrado</span>
										@endif
									</td>
									<td class="text-wrap" style="max-width: 260px;">{{ $firstObservation ?: '—' }}</td>
								</tr>
							@empty
								<tr>
									<td colspan="8" class="text-center text-muted py-4">
										<i class="ri-calendar-event-line fs-24 d-block mb-1 opacity-50"></i>
										Esta herramienta no tiene registros de control aún.
									</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		@if($tool->requires_calibration)
			<div class="card mt-3">
				<div class="card-header d-flex justify-content-between align-items-center border-bottom">
					<h5 class="card-title mb-0"><i class="ri-flask-line me-1 text-primary"></i> Bitácora de Calibración</h5>
					<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddCalibration">
						<i class="ri-add-line me-1"></i> Registrar
					</button>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table align-middle text-nowrap table-hover table-centered mb-0">
							<thead class="bg-light-subtle">
								<tr>
									<th>Folio</th>
									<th>Calibración</th>
									<th>Vencimiento</th>
									<th>Archivo</th>
									<th>Observaciones</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								@forelse($tool->calibrations as $calibration)
									<tr>
										<td class="fw-medium fs-13">{{ $calibration->folio ?: '—' }}</td>
										<td class="text-muted fs-12">{{ $calibration->calibration_date?->format('d/m/Y') ?? '—' }}</td>
										<td class="text-muted fs-12">{{ $calibration->expiry_date?->format('d/m/Y') ?? '—' }}</td>
										<td>
											@if($calibration->file_path)
												<a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($calibration->file_path) }}" target="_blank" class="btn btn-light btn-sm" title="Ver archivo">
													<i class="ri-file-download-line"></i>
												</a>
											@else
												<span class="text-muted fs-12">—</span>
											@endif
										</td>
										<td class="fs-12 text-muted" style="max-width: 220px; white-space: normal;">{{ $calibration->observations ? \Illuminate\Support\Str::limit($calibration->observations, 90) : '—' }}</td>
										<td class="text-end">
											<form action="{{ route('tool_calibrations.destroy', [$tool, $calibration]) }}" method="POST" onsubmit="return confirm('¿Eliminar esta calibración?')">
												@csrf @method('DELETE')
												<button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
													<i class="ri-delete-bin-line"></i>
												</button>
											</form>
										</td>
									</tr>
								@empty
									<tr>
										<td colspan="6" class="text-center text-muted py-4">
											<i class="ri-flask-line fs-24 d-block mb-1 opacity-50"></i>
											Sin entradas de calibración.
										</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		@endif
	</div>
</div>

<div class="modal fade" id="modalCreateControlFromDetail" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form action="{{ route('tool_controls.store') }}" method="POST">
				@csrf
				<input type="hidden" name="tool_id" value="{{ $tool->id }}">
				<div class="modal-header">
					<h5 class="modal-title"><i class="ri-add-circle-line me-1"></i> Nuevo Registro de Control</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Obra <span class="text-danger">*</span></label>
						<select name="project_work_id" id="create_control_project_work_id" class="form-select js-work-select @error('project_work_id') is-invalid @enderror" required>
							<option value="">Buscar y seleccionar una obra...</option>
							@foreach ($projectWorks as $projectWork)
								<option value="{{ $projectWork->id }}" @selected((string) old('project_work_id') === (string) $projectWork->id)>
									{{ $projectWork->project?->name ?? 'Sin proyecto' }} · {{ $projectWork->name }}
								</option>
							@endforeach
						</select>
						@error('project_work_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
					</div>

					<div class="row g-2">
						<div class="col-md-12">
							<label class="form-label">Responsable <span class="text-danger">*</span></label>
							<input type="text" name="responsible" class="form-control @error('responsible') is-invalid @enderror" value="{{ old('responsible') }}" maxlength="150" required>
							@error('responsible') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-6">
							<label class="form-label">Tipo de préstamo <span class="text-danger">*</span></label>
							<select name="loan_type" class="form-select @error('loan_type') is-invalid @enderror" required>
								<option value="fixed" @selected(old('loan_type', 'fixed') === 'fixed')>Fijo</option>
								<option value="provisional" @selected(old('loan_type') === 'provisional')>Provisional</option>
							</select>
							@error('loan_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-6">
							<label class="form-label">Estatus <span class="text-danger">*</span></label>
							<select name="status" class="form-select @error('status') is-invalid @enderror" required>
								<option value="active" @selected(old('status', 'active') === 'active')>Activo</option>
								<option value="closed" @selected(old('status') === 'closed')>Cerrado</option>
							</select>
							@error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-6">
							<label class="form-label">Fecha de salida <span class="text-danger">*</span></label>
							<input type="date" name="checkout_date" class="form-control @error('checkout_date') is-invalid @enderror" value="{{ old('checkout_date', now()->format('Y-m-d')) }}" required>
							@error('checkout_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-6">
							<label class="form-label">Fecha de revisión</label>
							<input type="date" name="review_date" class="form-control @error('review_date') is-invalid @enderror" value="{{ old('review_date') }}">
							@error('review_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
					</div>

					<div class="mt-3">
						<label class="form-label">Observaciones</label>
						<textarea name="observations_text" class="form-control @error('observations_text') is-invalid @enderror" rows="3" maxlength="2000" placeholder="Notas opcionales sobre el uso de la herramienta...">{{ old('observations_text') }}</textarea>
						@error('observations_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Guardar Control</button>
				</div>
			</form>
		</div>
	</div>
</div>

@if($tool->requires_calibration)
<div class="modal fade" id="modalAddCalibration" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<form action="{{ route('tool_calibrations.store', $tool) }}" method="POST" enctype="multipart/form-data">
				@csrf
				<div class="modal-header">
					<div>
						<h5 class="modal-title"><i class="ri-flask-line me-1"></i> Registrar calibración</h5>
						<div class="text-muted fs-13 mt-1"><i class="ri-hashtag me-1"></i>Folio {{ $nextCalibrationFolio }}</div>
					</div>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label fw-medium">Fecha de calibración <span class="text-danger">*</span></label>
							<input type="date" name="calibration_date" class="form-control" value="{{ old('calibration_date', now()->format('Y-m-d')) }}" required>
						</div>
						<div class="col-md-6">
							<label class="form-label fw-medium">Fecha de vencimiento</label>
							<input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date') }}">
						</div>
						<div class="col-md-6">
							<label class="form-label fw-medium">Archivo <span class="text-muted fw-normal fs-12">(PDF / imagen)</span></label>
							<input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
						</div>
						<div class="col-12">
							<label class="form-label fw-medium">Observaciones</label>
							<textarea name="observations" class="form-control" rows="3" maxlength="2000" placeholder="Notas de calibración...">{{ old('observations') }}</textarea>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Registrar</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endif

@php
	$selectedCategoryForEdit = $tool->category;
	$rootCategoryForEdit = $selectedCategoryForEdit?->parent_id ? $selectedCategoryForEdit->parent : $selectedCategoryForEdit;
	$subcategoryForEdit = $selectedCategoryForEdit?->parent_id ? $selectedCategoryForEdit : null;
@endphp

<div class="modal fade" id="modalEditTool" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<form action="{{ route('tools.update', $tool) }}" method="POST">
				@csrf
				@method('PUT')
				<div class="modal-header">
					<h5 class="modal-title"><i class="ri-edit-line me-1"></i> Editar Herramienta</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">Categoría <span class="text-danger">*</span></label>
							<select name="root_category_id" id="edit_root_category_id" class="form-select js-category-select @error('root_category_id') is-invalid @enderror" required>
								<option value="">Seleccionar categoría...</option>
								@foreach ($rootCategories as $rootCategory)
									<option value="{{ $rootCategory->id }}" @selected((string) old('root_category_id', $rootCategoryForEdit?->id) === (string) $rootCategory->id)>
										{{ $rootCategory->name }}
									</option>
								@endforeach
							</select>
							@error('root_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>

						<div class="col-md-6">
							<label class="form-label">Subcategoría</label>
							<select name="subcategory_id" id="edit_subcategory_id" class="form-select js-subcategory-select @error('subcategory_id') is-invalid @enderror">
								<option value="">Sin subcategoría</option>
							</select>
							<div class="form-text">Si la categoría tiene subcategorías, selecciona una. Si no tiene, déjalo vacío.</div>
							@error('subcategory_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>

						<div class="col-md-4">
							<label class="form-label">Número Económico <span class="text-danger">*</span></label>
							<input type="text" name="economic_number" class="form-control @error('economic_number') is-invalid @enderror" value="{{ old('economic_number', $tool->economic_number) }}" required maxlength="100" autocomplete="off">
							@error('economic_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-8">
							<label class="form-label">Descripción <span class="text-danger">*</span></label>
							<textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2" required maxlength="500">{{ old('description', $tool->description) }}</textarea>
							@error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-4">
							<label class="form-label">Marca</label>
							<input type="text" name="brand" class="form-control @error('brand') is-invalid @enderror" value="{{ old('brand', $tool->brand) }}" maxlength="120">
							@error('brand') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-4">
							<label class="form-label">Modelo</label>
							<input type="text" name="model" class="form-control @error('model') is-invalid @enderror" value="{{ old('model', $tool->model) }}" maxlength="120">
							@error('model') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-4">
							<label class="form-label">Número de Serie</label>
							<input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror" value="{{ old('serial_number', $tool->serial_number) }}" maxlength="120">
							@error('serial_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-4">
							<label class="form-label">Estatus <span class="text-danger">*</span></label>
							<select name="status" class="form-select @error('status') is-invalid @enderror" required>
								<option value="active" @selected(old('status', $tool->status) === 'active')>Activa</option>
								<option value="in_service" @selected(old('status', $tool->status) === 'in_service')>En Servicio</option>
								<option value="inactive" @selected(old('status', $tool->status) === 'inactive')>Inactiva</option>
							</select>
							@error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
						</div>
						<div class="col-md-8 d-flex align-items-end">
							<div class="form-check form-switch mb-1">
								<input class="form-check-input" type="checkbox" role="switch" id="edit_requires_calibration" name="requires_calibration" value="1" @checked(old('requires_calibration', $tool->requires_calibration))>
								<label class="form-check-label" for="edit_requires_calibration">Requiere calibración</label>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Actualizar</button>
				</div>
			</form>
		</div>
	</div>
</div>

@endsection

@push('scripts')
<script>
function initSearchableSelect(element, placeholder) {
	if (!element || element.dataset.choicesReady === '1' || typeof Choices === 'undefined') {
		return;
	}

	element._choicesInstance = new Choices(element, {
		searchEnabled: true,
		itemSelectText: '',
		searchPlaceholderValue: placeholder,
		noResultsText: 'Sin resultados',
		noChoicesText: 'Sin opciones disponibles',
		shouldSort: false,
		maxItemCount: 1,
	});

	element.dataset.choicesReady = '1';
}

function syncChoicesOptions(selectEl) {
	if (!selectEl || !selectEl._choicesInstance) {
		return;
	}

	var selectedValue = selectEl.value;
	var choices = Array.from(selectEl.options).map(function (option) {
		return {
			value: option.value,
			label: option.text,
			selected: String(option.value) === String(selectedValue),
			disabled: option.disabled,
		};
	});

	selectEl._choicesInstance.clearChoices();
	selectEl._choicesInstance.setChoices(choices, 'value', 'label', true);
}

function loadToolSubcategories(rootCategoryId, selectEl, preselectId) {
	selectEl.innerHTML = '<option value="">Sin subcategoría</option>';
	syncChoicesOptions(selectEl);

	if (!rootCategoryId) {
		return;
	}

	fetch('/categorias-herramientas/' + rootCategoryId + '/subcategorias-json', {
		headers: { 'X-Requested-With': 'XMLHttpRequest' }
	})
	.then(function (response) { return response.json(); })
	.then(function (subcategories) {
		subcategories.forEach(function (subcategory) {
			var option = document.createElement('option');
			option.value = subcategory.id;
			option.textContent = subcategory.name;
			if (preselectId && String(subcategory.id) === String(preselectId)) {
				option.selected = true;
			}
			selectEl.appendChild(option);
		});

		syncChoicesOptions(selectEl);
	});
}

(function () {
	var editRootCategory = document.getElementById('edit_root_category_id');
	var editSubcategory = document.getElementById('edit_subcategory_id');
	var createControlWork = document.getElementById('create_control_project_work_id');

	initSearchableSelect(editRootCategory, 'Buscar categoría...');
	initSearchableSelect(editSubcategory, 'Buscar subcategoría...');
	initSearchableSelect(createControlWork, 'Buscar obra...');

	if (!editRootCategory || !editSubcategory) {
		return;
	}

	editRootCategory.addEventListener('change', function () {
		loadToolSubcategories(this.value, editSubcategory, null);
	});

	loadToolSubcategories(
		editRootCategory.value,
		editSubcategory,
		'{{ old('subcategory_id', $subcategoryForEdit?->id) }}'
	);
}());

(function () {
	if (typeof Dropzone === 'undefined') {
		return;
	}

	document.querySelectorAll('.photo-dz-zone').forEach(function (el) {
		var slot = el.dataset.slot;
		var uploadUrl = el.dataset.uploadUrl;
		var csrf = el.dataset.csrf;

		var dz = new Dropzone(el, {
			url: uploadUrl,
			method: 'POST',
			paramName: 'file',
			maxFiles: 1,
			maxFilesize: 5,
			acceptedFiles: 'image/jpeg,image/jpg,image/png,image/webp',
			autoProcessQueue: true,
			addRemoveLinks: false,
			clickable: true,
			headers: { 'X-CSRF-TOKEN': csrf },
			dictDefaultMessage: '',
		});

		dz.on('sending', function () {
			document.getElementById('dz-error-' + slot).textContent = '';
		});

		dz.on('success', function (file, response) {
			if (response && response.url) {
				var preview = document.getElementById('photo-preview-' + slot);
				var imgEl = preview.querySelector('img');
				var viewBtn = preview.querySelector('.photo-view-btn');

				imgEl.src = response.url;
				if (viewBtn) {
					viewBtn.href = response.url;
				}

				preview.classList.remove('d-none');
				el.classList.add('d-none');

				var colEl = el.closest('.col-md-4');
				if (colEl && !colEl.querySelector('.btn-replace-photo')) {
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'btn btn-sm btn-outline-secondary mt-2 w-100 btn-replace-photo';
					btn.dataset.slot = slot;
					btn.innerHTML = '<i class="ri-refresh-line me-1"></i> Reemplazar';
					btn.addEventListener('click', function () {
						toggleReplace(slot);
					});
					colEl.appendChild(btn);
				}
			}

			dz.removeFile(file);
		});

		dz.on('error', function (file, message) {
			var errEl = document.getElementById('dz-error-' + slot);
			errEl.textContent = typeof message === 'string'
				? message
				: (message.error || 'Error al subir la imagen.');
			dz.removeFile(file);
		});
	});

	function toggleReplace(slot) {
		var preview = document.getElementById('photo-preview-' + slot);
		var dzZone = document.getElementById('dropzone-slot-' + slot);

		if (!dzZone || !preview) {
			return;
		}

		if (dzZone.classList.contains('d-none')) {
			dzZone.classList.remove('d-none');
			preview.classList.add('d-none');
		} else {
			dzZone.classList.add('d-none');
			preview.classList.remove('d-none');
		}
	}

	document.querySelectorAll('.btn-replace-photo').forEach(function (btn) {
		btn.addEventListener('click', function () {
			toggleReplace(this.dataset.slot);
		});
	});
}());
</script>
@endpush

