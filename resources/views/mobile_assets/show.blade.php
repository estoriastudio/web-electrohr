@extends('layouts.app')

@push('styles')
<style>
    /* ── Galería Dropzone ─────────────────────── */
    .photo-dz-wrap { position: relative; }
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
    /* Preview dentro del dropzone */
    .photo-dz-zone .dz-preview { margin: 0 !important; width: 100% !important; }
    .photo-dz-zone .dz-image { width: 100% !important; height: 158px !important; border-radius: 0 !important; }
    .photo-dz-zone .dz-image img { width: 100%; height: 100%; object-fit: cover; }
    .photo-dz-zone .dz-details { display: none !important; }
    .photo-dz-zone .dz-success-mark, .photo-dz-zone .dz-error-mark { display: none !important; }
    .photo-dz-zone .dz-progress { display: none !important; }
    /* Foto existente */
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
    /* ── Semáforo ─────────────────────────────── */
    .traffic-dot { width: 14px; height: 14px; border-radius: 50%; display: inline-block; }
    .traffic-gray   { background: #adb5bd; }
    .traffic-green  { background: #28a745; }
    .traffic-yellow { background: #ffc107; }
    .traffic-red    { background: #dc3545; }
</style>
@endpush

@section('page_title', $mobileAsset->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('mobile_assets.index', ['type' => $mobileAsset->type]) }}">Bienes Móviles</a></li>
    <li class="breadcrumb-item active">{{ $mobileAsset->name }}</li>
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

@php
    $typeLabels = [
        'parque_vehicular'  => 'Parque Vehicular',
        'maquinaria_pesada' => 'Maquinaria Pesada',
        'semiremolque'      => 'SemiRemolque',
    ];
    $statusMap = [
        'activo'     => ['label' => 'Activo',         'class' => 'bg-success-subtle text-success'],
        'vendido'    => ['label' => 'Vendido',         'class' => 'bg-secondary-subtle text-secondary'],
        'obsoleto'   => ['label' => 'Obsoleto',        'class' => 'bg-warning-subtle text-warning'],
        'reparacion' => ['label' => 'En reparación',   'class' => 'bg-danger-subtle text-danger'],
    ];
    $statusBadge = $statusMap[$mobileAsset->status] ?? ['label' => $mobileAsset->status, 'class' => 'bg-secondary-subtle text-secondary'];
@endphp

{{-- ── Encabezado ──────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h5 class="fw-semibold mb-1">{{ $mobileAsset->name }}</h5>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            @if ($mobileAsset->folio)
                <span class="text-muted fs-13"><i class="ri-hashtag text-primary"></i> {{ $mobileAsset->folio }}</span>
            @endif
            @if ($mobileAsset->type)
                <span class="badge bg-primary-subtle text-primary fs-12">{{ $typeLabels[$mobileAsset->type] ?? $mobileAsset->type }}</span>
            @endif
            <span class="badge {{ $statusBadge['class'] }} fs-12">{{ $statusBadge['label'] }}</span>
        </div>
    </div>
    @role('admin|Moviles')
    <a href="{{ route('mobile_assets.edit', $mobileAsset) }}" class="btn btn-primary btn-sm">
        <i class="ri-edit-line me-1"></i> Editar bien
    </a>
    @endrole
</div>

{{-- ── Layout principal ──────────────────────────────────────────────── --}}
<div class="row g-3">

    {{-- ── Columna izquierda (8/12) ──────────────────────────────────── --}}
    <div class="col-xl-8">

        {{-- ── Galería de fotos ──────────────────────────────────────── --}}
        <div class="card mb-3">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-image-line me-1 text-muted"></i> Fotografías</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @for ($slot = 1; $slot <= 3; $slot++)
                        @php
                            $photoUrl  = $mobileAsset->photoUrl($slot);
                            $uploadUrl = route('mobile_assets.photo.upload', [$mobileAsset, $slot]);
                            $deleteUrl = route('mobile_assets.photo.delete', [$mobileAsset, $slot]);
                        @endphp
                        <div class="col-md-4">
                            <p class="photo-dz-label mb-1">Foto {{ $slot }}</p>

                            {{-- Foto existente con overlay de acciones --}}
                            @if ($photoUrl)
                                <div class="photo-existing" id="photo-preview-{{ $slot }}">
                                    <img src="{{ $photoUrl }}" alt="Foto {{ $slot }}">
                                    <div class="photo-existing-overlay">
                                        <a href="{{ $photoUrl }}" target="_blank"
                                           class="btn btn-sm btn-light" title="Ver en tamaño completo">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        @role('admin|Moviles')
                                        <form action="{{ $deleteUrl }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar esta fotografía?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                        @endrole
                                    </div>
                                </div>
                            @else
                                <div class="photo-existing d-none" id="photo-preview-{{ $slot }}">
                                    <img src="" alt="Foto {{ $slot }}">
                                    <div class="photo-existing-overlay">
                                        <a href="#" target="_blank" class="btn btn-sm btn-light photo-view-btn" title="Ver en tamaño completo">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        @role('admin|Moviles')
                                        <form action="{{ $deleteUrl }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar esta fotografía?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                        @endrole
                                    </div>
                                </div>
                            @endif

                            {{-- Dropzone (visible si no hay foto; oculto si ya hay) --}}
                            @role('admin|Moviles')
                            <div class="photo-dz-zone {{ $photoUrl ? 'd-none' : '' }}"
                                 id="dropzone-slot-{{ $slot }}"
                                 data-slot="{{ $slot }}"
                                 data-upload-url="{{ $uploadUrl }}"
                                 data-csrf="{{ csrf_token() }}">
                                <div class="dz-message needsclick">
                                    <i class="ri-image-add-line"></i>
                                    <span>Arrastra o <strong>haz clic</strong><br>JPG, PNG, WEBP — máx. 5 MB</span>
                                </div>
                            </div>
                            <div class="photo-dz-error" id="dz-error-{{ $slot }}"></div>

                            {{-- Botón para reemplazar foto existente --}}
                            @if ($photoUrl)
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-2 w-100 btn-replace-photo"
                                        data-slot="{{ $slot }}" title="Reemplazar foto">
                                    <i class="ri-refresh-line me-1"></i> Reemplazar
                                </button>
                            @endif
                            @endrole
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        {{-- ── Checklist documental ───────────────────────────────────── --}}
        <div class="card mb-3">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-file-shield-2-line me-1 text-muted"></i> Documentación</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-centered">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th style="width: 20px;"></th>
                                <th>Documento</th>
                                <th>Vencimiento</th>
                                <th>Archivo</th>
                                @role('admin|Moviles')
                                <th>Actualizar</th>
                                @endrole
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($applicableDocTypes as $dt)
                                @php
                                    $docRecord  = $mobileAsset->documents->firstWhere('document_type', $dt);
                                    $ds         = $docRecord ? $docRecord->status : 'gray';
                                    $hasExpiry  = ! in_array($dt, $noExpiryDocs);
                                @endphp
                                <tr>
                                    <td>
                                        <span class="traffic-dot traffic-{{ $ds }}" title="{{ ucfirst($ds) }}"></span>
                                    </td>
                                    <td class="fw-medium fs-14">{{ $docLabels[$dt] ?? $dt }}</td>
                                    <td class="fs-13 text-muted">
                                        @if (! $hasExpiry)
                                            <span class="text-muted fst-italic">Sin vencimiento</span>
                                        @elseif ($docRecord && $docRecord->expiry_date)
                                            @php
                                                $daysLeft = now()->diffInDays($docRecord->expiry_date, false);
                                            @endphp
                                            <span class="{{ $ds === 'red' ? 'text-danger fw-semibold' : ($ds === 'yellow' ? 'text-warning fw-semibold' : '') }}">
                                                {{ $docRecord->expiry_date->format('d/m/Y') }}
                                                @if ($daysLeft <= 0)
                                                    <small class="d-block text-danger">Vencido</small>
                                                @elseif ($daysLeft <= 90)
                                                    <small class="d-block text-warning">{{ $daysLeft }} días restantes</small>
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($docRecord && $docRecord->file_path)
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($docRecord->file_path) }}"
                                               target="_blank" class="btn btn-light btn-sm" title="Ver documento">
                                                <i class="ri-file-download-line"></i>
                                            </a>
                                        @else
                                            <span class="text-muted fs-12">Sin archivo</span>
                                        @endif
                                    </td>
                                    @role('admin|Moviles')
                                    <td>
                                        <button type="button" class="btn btn-soft-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDoc_{{ $dt }}">
                                            <i class="ri-upload-2-line me-1"></i>
                                            {{ $docRecord && $docRecord->file_path ? 'Reemplazar' : 'Subir' }}
                                        </button>
                                    </td>
                                    @endrole
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Sin documentos aplicables para este tipo de bien.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── Bitácora de mantenimiento ──────────────────────────────── --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-file-list-3-line me-1 text-muted"></i> Bitácora de Mantenimiento
                </h5>
                @role('admin|Moviles')
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modalAddLog">
                    <i class="ri-add-line me-1"></i> Registrar
                </button>
                @endrole
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Próximo</th>
                                <th>Km a vencer</th>
                                <th>Evidencia</th>
                                <th>Notas</th>
                                @role('admin|Moviles')
                                <th></th>
                                @endrole
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($mobileAsset->maintenanceLogs as $log)
                                <tr>
                                    <td class="fw-medium fs-13">{{ $log->folio ?? '—' }}</td>
                                    <td class="text-muted fs-12">{{ $log->maintenance_date->format('d/m/Y') }}</td>
                                    <td class="fs-12">
                                        @if ($log->next_maintenance_date)
                                            @php
                                                $daysToNext = now()->diffInDays($log->next_maintenance_date, false);
                                            @endphp
                                            <span class="{{ $daysToNext < 0 ? 'text-danger' : ($daysToNext <= 14 ? 'text-warning' : 'text-muted') }}">
                                                {{ $log->next_maintenance_date->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="fs-12 text-muted">
                                        {{ $log->mileage_due ? number_format($log->mileage_due) . ' km' : '—' }}
                                    </td>
                                    <td>
                                        @if ($log->inspection_file)
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($log->inspection_file) }}"
                                               target="_blank" class="btn btn-light btn-sm" title="Ver archivo">
                                                <i class="ri-file-download-line"></i>
                                            </a>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td class="fs-12 text-muted" style="max-width: 200px; white-space: normal;">
                                        {{ $log->notes ? \Illuminate\Support\Str::limit($log->notes, 80) : '—' }}
                                    </td>
                                    @role('admin|Moviles')
                                    <td>
                                        <form action="{{ route('maintenance_logs.destroy', [$mobileAsset, $log]) }}"
                                              method="POST"
                                              onsubmit="return confirm('¿Eliminar esta entrada de la bitácora?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </td>
                                    @endrole
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-file-list-3-line fs-24 d-block mb-1 opacity-50"></i>
                                        Sin entradas en la bitácora.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>{{-- /col-xl-8 --}}

    {{-- ── Columna derecha (4/12) — Ficha técnica ──────────────────────── --}}
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-information-line me-1 text-muted"></i> Ficha Técnica
                </h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0 fs-13">
                    <dt class="col-sm-5 text-muted fw-normal">Número Económico</dt>
                    <dd class="col-sm-7 fw-medium">{{ $mobileAsset->folio ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Póliza</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->policy ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">No. Tarjeta</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->card_number ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Kilometraje</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->milage ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Nombre</dt>
                    <dd class="col-sm-7 fw-medium">{{ $mobileAsset->name }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Marca</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->brand ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Modelo</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->model ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Año</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->year ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Serie / NIV</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->serial ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Color</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->color ?? '—' }}</dd>

                    @if ($mobileAsset->type === 'parque_vehicular')
                    <dt class="col-sm-5 text-muted fw-normal">Placas</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->plates ?? '—' }}</dd>
                    @endif

                    <dt class="col-sm-5 text-muted fw-normal">Función</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->asset_function ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Operador</dt>
                    <dd class="col-sm-7">{{ $mobileAsset->operator ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal">Estatus</dt>
                    <dd class="col-sm-7">
                        <span class="badge {{ $statusBadge['class'] }} fs-11">{{ $statusBadge['label'] }}</span>
                    </dd>
                </dl>
            </div>
        </div>

        {{-- Semáforo resumen --}}
        <div class="card mt-3">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-file-shield-2-line me-1 text-muted"></i> Estado Documental
                </h5>
            </div>
            <div class="card-body">
                @foreach ($applicableDocTypes as $dt)
                    @php
                        $docRecord = $mobileAsset->documents->firstWhere('document_type', $dt);
                        $ds = $docRecord ? $docRecord->status : 'gray';
                    @endphp
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="traffic-dot traffic-{{ $ds }} shrink-0"></span>
                        <span class="fs-13 {{ $ds === 'red' ? 'text-danger fw-semibold' : ($ds === 'yellow' ? 'text-warning fw-semibold' : 'text-muted') }}">
                            {{ $docLabels[$dt] ?? $dt }}
                        </span>
                    </div>
                @endforeach
                @if (empty($applicableDocTypes))
                    <p class="text-muted fs-13 mb-0">Sin documentos configurados.</p>
                @endif

                <div class="mt-3 pt-3 border-top">
                    <a href="{{ route('mobile_assets.documents.zip', $mobileAsset) }}"
                       class="btn btn-primary btn-sm w-100 {{ $hasDownloadableDocuments ? '' : 'disabled' }}"
                       @if (! $hasDownloadableDocuments) aria-disabled="true" @endif>
                        <i class="ri-folder-zip-line me-1"></i> Descargar documentación (.zip)
                    </a>
                </div>
            </div>
        </div>

    </div>{{-- /col-xl-4 --}}

</div>{{-- /row --}}


{{-- ══════════════════════════════════════════════════════════════
     MODALES — Documentos (uno por tipo)
══════════════════════════════════════════════════════════════════ --}}
@role('admin|Moviles')
@foreach ($applicableDocTypes as $dt)
    @php
        $docRecord = $mobileAsset->documents->firstWhere('document_type', $dt);
        $hasExpiry = ! in_array($dt, $noExpiryDocs);
    @endphp
    <div class="modal fade" id="modalDoc_{{ $dt }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('mobile_assets.document.update', [$mobileAsset, $dt]) }}"
                      method="POST" enctype="multipart/form-data">
                    @csrf @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="ri-file-add-line me-1"></i> {{ $docLabels[$dt] ?? $dt }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Archivo <span class="text-muted fw-normal fs-12">(PDF, JPG, PNG — máx. 10 MB)</span></label>
                            <input type="file" name="file" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png">
                            @if ($docRecord && $docRecord->file_path)
                                <div class="form-text">Ya existe un archivo subido. Sube uno nuevo para reemplazarlo.</div>
                            @endif
                        </div>
                        @if ($hasExpiry)
                        <div class="mb-3">
                            <label class="form-label fw-medium">Fecha de vencimiento</label>
                            <input type="date" name="expiry_date" class="form-control"
                                   value="{{ $docRecord && $docRecord->expiry_date ? $docRecord->expiry_date->format('Y-m-d') : '' }}">
                        </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Registrar bitácora de mantenimiento
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalAddLog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('maintenance_logs.store', $mobileAsset) }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title"><i class="ri-file-list-3-line me-1"></i> Registrar mantenimiento</h5>
                        <div class="text-muted fs-13 mt-1">
                            <i class="ri-hashtag me-1"></i>Folio {{ $nextMaintenanceFolio }}
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-medium">
                                Fecha de mantenimiento <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="maintenance_date" class="form-control"
                                   value="{{ old('maintenance_date', now()->format('Y-m-d')) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-medium">Fecha próximo mantenimiento</label>
                            <input type="date" name="next_maintenance_date" class="form-control"
                                   value="{{ old('next_maintenance_date') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-medium">Kilometraje a vencer</label>
                            <input type="number" name="mileage_due" class="form-control"
                                   min="0" step="1" placeholder="Ej. 120000" value="{{ old('mileage_due') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-medium">Archivo de inspección <span class="text-muted fw-normal fs-12">(PDF / imagen)</span></label>
                            <input type="file" name="inspection_file" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-medium">Observaciones</label>
                            <textarea name="notes" class="form-control" rows="3"
                                      placeholder="Describe el trabajo realizado…">{{ old('notes') }}</textarea>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endrole

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Inicializar un Dropzone por cada slot ──────────────────────────
    document.querySelectorAll('.photo-dz-zone').forEach(function (el) {
        var slot      = el.dataset.slot;
        var uploadUrl = el.dataset.uploadUrl;
        var csrf      = el.dataset.csrf;

        var dz = new Dropzone(el, {
            url:              uploadUrl,
            method:           'POST',
            paramName:        'file',
            maxFiles:         1,
            maxFilesize:      5,           // MB
            acceptedFiles:    'image/jpeg,image/jpg,image/png,image/webp',
            autoProcessQueue: true,
            addRemoveLinks:   false,
            clickable:        true,
            headers:          { 'X-CSRF-TOKEN': csrf },
            // Usamos el mensaje nativo del tema (evitar que DZ lo sobreescriba)
            dictDefaultMessage: '',
        });

        dz.on('sending', function (file, xhr, formData) {
            document.getElementById('dz-error-' + slot).textContent = '';
        });

        dz.on('success', function (file, response) {
            if (response && response.url) {
                var preview  = document.getElementById('photo-preview-' + slot);
                var imgEl    = preview.querySelector('img');
                var viewBtn  = preview.querySelector('.photo-view-btn');

                // Actualizar imagen
                imgEl.src = response.url;
                if (viewBtn) viewBtn.href = response.url;

                // Mostrar el preview y ocultar el dropzone
                preview.classList.remove('d-none');
                el.classList.add('d-none');

                // Mostrar botón "Reemplazar" si no existe
                var colEl = el.closest('.col-md-4');
                if (colEl && ! colEl.querySelector('.btn-replace-photo')) {
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

    // ── Botón "Reemplazar" — alterna dropzone para slots con foto ─────
    function toggleReplace(slot) {
        var preview = document.getElementById('photo-preview-' + slot);
        var dzZone  = document.getElementById('dropzone-slot-' + slot);
        if (dzZone) {
            var isHidden = dzZone.classList.contains('d-none');
            if (isHidden) {
                dzZone.classList.remove('d-none');
                preview.classList.add('d-none');
            } else {
                dzZone.classList.add('d-none');
                preview.classList.remove('d-none');
            }
        }
    }

    document.querySelectorAll('.btn-replace-photo').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleReplace(this.dataset.slot);
        });
    });

})();
</script>
@endpush
