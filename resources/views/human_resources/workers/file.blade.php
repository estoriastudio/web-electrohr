@extends('layouts.app')

@section('page_title', 'Expediente')

@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('human_resources.workers.index') }}">Trabajadores</a></li><li class="breadcrumb-item"><a href="{{ route('human_resources.workers.show', $worker) }}">{{ $worker->first_name }} {{ $worker->last_name }}</a></li><li class="breadcrumb-item active">Expediente</li>@endsection

@section('content')
@include('human_resources.partials.flash')
@php($documents = [
	'ine_path' => ['label' => 'INE', 'expiration' => 'ine_expiration_date', 'worker_expiration' => true],
	'birth_certificate_path' => ['label' => 'Acta de nacimiento', 'expiration' => 'birth_certificate_expiration_date'],
	'address_proof_path' => ['label' => 'Comprobante de domicilio', 'expiration' => 'address_proof_expiration_date'],
	'nss_path' => ['label' => 'Comprobante NSS', 'expiration' => 'nss_expiration_date'],
	'license_path' => ['label' => 'Licencia', 'expiration' => 'license_expiration_date'],
	'tax_status_path' => ['label' => 'Constancia fiscal', 'expiration' => 'tax_status_expiration_date'],
	'medical_certificate_path' => ['label' => 'Certificado médico', 'expiration' => 'medical_certificate_expiration_date', 'worker_expiration' => true],
	'cv_path' => ['label' => 'Currículum', 'expiration' => 'cv_expiration_date'],
	'emergency_contact_ine_path' => ['label' => 'INE contacto de emergencia', 'expiration' => 'emergency_contact_ine_expiration_date'],
	'professional_title_path' => ['label' => 'Título profesional (opcional)'],
	'professional_license_path' => ['label' => 'Cédula profesional (opcional)'],
])
<div class="card">
	<div class="card-header d-flex justify-content-between align-items-center">
		<div>
			<h4 class="card-title mb-0">Expediente de {{ $worker->first_name }} {{ $worker->last_name }}</h4>
			<span class="text-muted fs-12">Los documentos se almacenan de forma privada.</span>
		</div>
		<a class="btn btn-light btn-sm" href="{{ route('human_resources.workers.show', $worker) }}">Volver al trabajador</a>
	</div>
	<div class="card-body">
		@if($canManageWorkerFile)
		<form method="POST" enctype="multipart/form-data" action="{{ route('human_resources.workers.file.update', $worker) }}">
			@csrf
			@method('PUT')
		@endif
			<div class="row g-3">
				@foreach($documents as $field => $document)
					<div class="col-lg-6">
						<div class="border rounded p-3 h-100">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<label class="form-label mb-0" for="{{ $field }}">{{ $document['label'] }}</label>
								@if($workerFile->{$field})
									<a class="btn btn-light btn-sm" href="{{ route('human_resources.workers.file.download', [$worker, $field]) }}" title="Descargar {{ $document['label'] }}"><i class="ri-download-2-line"></i></a>
								@endif
							</div>
							@if($canManageWorkerFile)
								<input id="{{ $field }}" name="{{ $field }}" type="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control mb-2">
							@endif
							@if($canManageWorkerFile && isset($document['expiration']))
								<label class="form-label fs-12" for="{{ $document['expiration'] }}">Fecha de vencimiento</label>
								<input id="{{ $document['expiration'] }}" name="{{ $document['expiration'] }}" type="date" class="form-control" value="{{ old($document['expiration'], ($document['worker_expiration'] ?? false ? $worker->{$document['expiration']} : $workerFile->{$document['expiration']})?->format('Y-m-d')) }}">
							@endif
						</div>
					</div>
				@endforeach
				@if($canManageWorkerFile)<div class="col-12">
					<label class="form-label" for="notes">Notas</label>
					<textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $workerFile->notes) }}</textarea>
				</div>@endif
			</div>
			@if($canManageWorkerFile)<div class="mt-4"><button class="btn btn-primary" type="submit">Guardar expediente</button></div>@endif
		@if($canManageWorkerFile)</form>@endif
	</div>
</div>

<div class="card mt-3">
	<div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
		<div>
			<h4 class="card-title mb-0"><i class="ri-award-line me-2 text-primary"></i>Certificaciones DC3</h4>
			<span class="text-muted fs-12">{{ $worker->dc3s->count() }} certificación(es) registrada(s)</span>
		</div>
	</div>
	<div class="card-body">
		@if($worker->dc3s->isNotEmpty())
			<div class="list-group mb-3">
				@foreach($worker->dc3s as $dc3)
					<div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
						<div><span class="fw-medium">{{ $dc3->label }}</span><span class="text-muted fs-12 ms-2">Registrado {{ $dc3->created_at->format('d/m/Y') }}</span></div>
						<div class="d-flex gap-1">
							<a class="btn btn-light btn-sm" href="{{ route('human_resources.workers.file.dc3.download', [$worker, $dc3]) }}" title="Descargar {{ $dc3->label }}"><i class="ri-download-2-line"></i></a>
							@if($canManageWorkerFile)<form method="POST" action="{{ route('human_resources.workers.file.dc3.destroy', [$worker, $dc3]) }}" onsubmit="return confirm('¿Eliminar esta certificación DC3?')">@csrf @method('DELETE')<button class="btn btn-soft-danger btn-sm" type="submit" title="Eliminar {{ $dc3->label }}"><i class="ri-delete-bin-line"></i></button></form>@endif
						</div>
					</div>
				@endforeach
			</div>
		@endif
		@if($canManageWorkerFile)<form method="POST" enctype="multipart/form-data" action="{{ route('human_resources.workers.file.dc3.store', $worker) }}" class="row g-3 align-items-end">
			@csrf
			<div class="col-md-5"><label class="form-label" for="dc3_label">Identificador</label><input id="dc3_label" name="label" type="text" class="form-control @error('label') is-invalid @enderror" value="{{ old('label') }}" placeholder="DC3-1" required>@error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
			<div class="col-md-5"><label class="form-label" for="dc3_file">Archivo</label><input id="dc3_file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control @error('file') is-invalid @enderror" required>@error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
			<div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit"><i class="ri-upload-2-line me-1"></i>Adjuntar</button></div>
		</form>@endif
	</div>
</div>
@endsection