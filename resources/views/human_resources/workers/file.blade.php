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
		<form method="POST" enctype="multipart/form-data" action="{{ route('human_resources.workers.file.update', $worker) }}">
			@csrf
			@method('PUT')
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
							<input id="{{ $field }}" name="{{ $field }}" type="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control mb-2">
							<label class="form-label fs-12" for="{{ $document['expiration'] }}">Fecha de vencimiento</label>
							<input id="{{ $document['expiration'] }}" name="{{ $document['expiration'] }}" type="date" class="form-control" value="{{ old($document['expiration'], ($document['worker_expiration'] ?? false ? $worker->{$document['expiration']} : $workerFile->{$document['expiration']})?->format('Y-m-d')) }}">
						</div>
					</div>
				@endforeach
				<div class="col-12">
					<label class="form-label" for="notes">Notas</label>
					<textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $workerFile->notes) }}</textarea>
				</div>
			</div>
			<div class="mt-4"><button class="btn btn-primary" type="submit">Guardar expediente</button></div>
		</form>
	</div>
</div>
@endsection