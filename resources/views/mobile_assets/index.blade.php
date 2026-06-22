@extends('layouts.app')

@section('page_title', 'Bienes Móviles')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Bienes Móviles</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
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

@php
    $tabs = [
        'parque_vehicular'  => ['label' => 'Parque Vehicular',  'icon' => 'ri-car-line'],
        'maquinaria_pesada' => ['label' => 'Maquinaria Pesada', 'icon' => 'ri-tools-line'],
        'semiremolque'      => ['label' => 'SemiRemolques',     'icon' => 'ri-truck-line'],
    ];
@endphp

<div class="card">
    <div class="card-header border-bottom d-flex align-items-center">
        {{-- Pestañas --}}
        <ul class="nav nav-tabs card-header-tabs flex-grow-1 me-3" role="tablist">
            @foreach ($tabs as $tabKey => $tabData)
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $type === $tabKey ? 'active' : '' }}"
                       href="{{ route('mobile_assets.index', array_merge(request()->except(['type','page']), ['type' => $tabKey])) }}">
                        <i class="{{ $tabData['icon'] }} me-1"></i>{{ $tabData['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>

        @hasanyrole('admin|Moviles')
        @can('create')
        <div class="d-flex gap-2 flex-shrink-0">
            <button type="button" class="btn btn-sm btn-soft-success"
                    data-bs-toggle="modal" data-bs-target="#modalImportAssets">
                <i class="ri-upload-2-line me-1"></i> Importar
            </button>
            <a href="{{ route('mobile_assets.export') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ri-download-2-line me-1"></i> Exportar
            </a>
            <button type="button" class="btn btn-sm btn-primary"
                    data-bs-toggle="modal" data-bs-target="#modalCreateAsset">
                <i class="ri-add-line me-1"></i> Nuevo bien
            </button>
        </div>
        @endcan
        @endhasanyrole
    </div>

    {{-- Barra de búsqueda y filtro semáforo --}}
    <div class="card-body border-bottom py-3">
        <form method="GET" action="{{ route('mobile_assets.index') }}" class="row g-2 align-items-center">
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                    <input type="text" name="search" value="{{ $search }}"
                           class="form-control" placeholder="Buscar por nombre, n° económico, marca, modelo…"
                           autocomplete="off">
                    @if ($search)
                        <a href="{{ route('mobile_assets.index', ['type' => $type]) }}"
                           class="btn btn-outline-secondary" title="Limpiar">
                            <i class="ri-close-line"></i>
                        </a>
                    @endif
                </div>
            </div>

            <div class="col-md-4 col-sm-7">
                <select name="doc_status" class="form-select form-select-sm">
                    <option value=""       {{ $docStatus === ''       ? 'selected' : '' }}>Todos los estados documentales</option>
                    <option value="green"  {{ $docStatus === 'green'  ? 'selected' : '' }}>✅ Documentación completa</option>
                    <option value="yellow" {{ $docStatus === 'yellow' ? 'selected' : '' }}>⚠️  Documentos por vencer</option>
                    <option value="red"    {{ $docStatus === 'red'    ? 'selected' : '' }}>🔴 Documentos vencidos</option>
                    <option value="gray"   {{ $docStatus === 'gray'   ? 'selected' : '' }}>⬜ Documentos pendientes</option>
                </select>
            </div>

            <div class="col-md-2 col-sm-5">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="ri-filter-line me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </div>

    {{-- Grid de tarjetas --}}
    <div class="card-body">
        @php
            $statusMap = [
                'activo'     => ['label' => 'Activo',         'class' => 'bg-success-subtle text-success'],
                'vendido'    => ['label' => 'Vendido',         'class' => 'bg-secondary-subtle text-secondary'],
                'obsoleto'   => ['label' => 'Obsoleto',        'class' => 'bg-warning-subtle text-warning'],
                'reparacion' => ['label' => 'En reparación',   'class' => 'bg-danger-subtle text-danger'],
            ];

            $trafficColors = [
                'green'  => ['bg' => 'bg-success', 'title' => 'Doc. Vigente'],
                'yellow' => ['bg' => 'bg-warning',  'title' => 'Doc. Por vencer'],
                'red'    => ['bg' => 'bg-danger',   'title' => 'Doc. Vencido'],
                'gray'   => ['bg' => 'bg-secondary opacity-50', 'title' => 'Doc. Pendientes'],
            ];

            $docLabels = \App\Models\MobileAssetDocument::labelsEs();
        @endphp

        @if ($mobileAssets->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="ri-car-line fs-1 d-block mb-2 opacity-25"></i>
                <p class="mb-0">No hay bienes registrados en esta categoría.</p>
            </div>
        @else
            <div class="row g-3">
                @foreach ($mobileAssets as $asset)
                    @php
                        $worstStatus = $asset->getWorstDocumentStatus();
                        $statusBadge = $statusMap[$asset->status] ?? ['label' => $asset->status, 'class' => 'bg-secondary-subtle text-secondary'];
                        $photoUrl    = $asset->photoUrl(1);
                        $docTypes    = $asset->getApplicableDocumentTypes();
                        $tc          = $trafficColors[$worstStatus] ?? $trafficColors['gray'];
                    @endphp
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card overflow-hidden h-100 border">

                            {{-- Imagen --}}
                            <div class="position-relative" style="height: 160px; background: #f8f9fa;">
                                @if ($photoUrl)
                                    <img src="{{ $photoUrl }}" alt="{{ $asset->name }}"
                                         class="w-100 h-100" style="object-fit: cover;">
                                @else
                                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                        <i class="{{ $tabs[$asset->type]['icon'] ?? 'ri-car-line' }} fs-1 opacity-25"></i>
                                    </div>
                                @endif

                                <span class="position-absolute top-0 end-0 m-2">
                                    <span class="badge {{ $statusBadge['class'] }} fs-11">{{ $statusBadge['label'] }}</span>
                                </span>

                                <span class="position-absolute top-0 start-0 m-2">
                                    <span class="badge {{ $tc['bg'] }} text-white fs-11"
                                          title="Documentación: {{ $tc['title'] }}">
                                        <i class="ri-file-shield-2-line me-1"></i>{{ $tc['title'] }}
                                    </span>
                                </span>
                            </div>

                            {{-- Cuerpo --}}
                            <div class="card-body pb-2">
                                <a href="{{ route('mobile_assets.show', $asset) }}"
                                   class="text-dark fw-semibold fs-15 text-decoration-none d-block mb-1">
                                    {{ $asset->name }}
                                </a>
                                <p class="text-muted mb-0 fs-12">
                                    @if ($asset->folio)
                                        <span class="me-2"><i class="ri-hashtag text-primary"></i>{{ $asset->folio }}</span>
                                    @endif
                                    @if ($asset->brand)<span class="me-2">{{ $asset->brand }}</span>@endif
                                    @if ($asset->year)<span>{{ $asset->year }}</span>@endif
                                </p>
                                @if ($asset->operator)
                                    <p class="text-muted mb-0 fs-12 mt-1">
                                        <i class="ri-user-line me-1"></i>{{ $asset->operator }}
                                    </p>
                                @endif

                                {{-- Dots semáforo por documento --}}
                                @if (count($docTypes))
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @foreach ($docTypes as $dt)
                                            @php
                                                $docRecord = $asset->documents->firstWhere('document_type', $dt);
                                                $ds  = $docRecord ? $docRecord->status : 'gray';
                                                $dtc = $trafficColors[$ds] ?? $trafficColors['gray'];
                                            @endphp
                                            <span class="rounded-circle d-inline-block {{ $dtc['bg'] }}"
                                                  style="width: 10px; height: 10px; cursor: default;"
                                                  data-bs-toggle="tooltip"
                                                  data-bs-placement="top"
                                                  title="{{ $docLabels[$dt] ?? $dt }}: {{ $dtc['title'] }}">
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Footer --}}
                            <div class="card-footer bg-light-subtle d-flex justify-content-between align-items-center border-top py-2">
                                <a href="{{ route('mobile_assets.show', $asset) }}" class="link-primary fw-medium fs-13">
                                    Ver perfil <i class="ri-arrow-right-line align-middle"></i>
                                </a>
                                @hasanyrole('admin|Moviles')
                                <div class="d-flex gap-1">
                                    @can('update')
                                        <a href="{{ route('mobile_assets.edit', $asset) }}"
                                           class="btn btn-soft-primary btn-sm" title="Editar">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                    @endcan
                                    @can('delete')
                                        <form action="{{ route('mobile_assets.destroy', $asset) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar {{ addslashes($asset->name) }}? Esta acción no se puede deshacer.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                                @endhasanyrole
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($mobileAssets->hasPages())
                <div class="d-flex justify-content-end mt-3">
                    {{ $mobileAssets->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL — Crear nuevo bien móvil
══════════════════════════════════════════════════════════════ --}}
@hasanyrole('admin|Moviles')
@can('create')
<div class="modal fade" id="modalCreateAsset" tabindex="-1" aria-labelledby="modalCreateAssetLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('mobile_assets.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateAssetLabel">
                        <i class="ri-add-line me-1"></i> Nuevo Bien Móvil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="create_name" class="form-label fw-medium">
                                Nombre <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="create_name" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="create_folio" class="form-label fw-medium">Número Económico</label>
                            <input type="text" id="create_folio" name="folio"
                                   class="form-control @error('folio') is-invalid @enderror"
                                   value="{{ old('folio') }}" placeholder="Ej. BM-001">
                            @error('folio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="create_brand" class="form-label fw-medium">Marca</label>
                            <input type="text" id="create_brand" name="brand"
                                   class="form-control @error('brand') is-invalid @enderror"
                                   value="{{ old('brand') }}">
                            @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label for="create_model" class="form-label fw-medium">Modelo</label>
                            <input type="text" id="create_model" name="model"
                                   class="form-control @error('model') is-invalid @enderror"
                                   value="{{ old('model') }}">
                            @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label for="create_year" class="form-label fw-medium">Año</label>
                            <input type="text" id="create_year" name="year"
                                   class="form-control @error('year') is-invalid @enderror"
                                   value="{{ old('year') }}" placeholder="2024">
                            @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="create_type" class="form-label fw-medium">Tipo</label>
                            <select id="create_type" name="type"
                                    class="form-select @error('type') is-invalid @enderror">
                                <option value="">— Seleccionar —</option>
                                <option value="parque_vehicular"  {{ old('type', $type) === 'parque_vehicular'  ? 'selected' : '' }}>Parque Vehicular</option>
                                <option value="maquinaria_pesada" {{ old('type', $type) === 'maquinaria_pesada' ? 'selected' : '' }}>Maquinaria Pesada</option>
                                <option value="semiremolque"      {{ old('type', $type) === 'semiremolque'      ? 'selected' : '' }}>SemiRemolque</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="create_operator" class="form-label fw-medium">Operador</label>
                            <input type="text" id="create_operator" name="operator"
                                   class="form-control @error('operator') is-invalid @enderror"
                                   value="{{ old('operator') }}">
                            @error('operator')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6" id="createPlatesField" style="display:none;">
                            <label for="create_plates" class="form-label fw-medium">Placas</label>
                            <input type="text" id="create_plates" name="plates"
                                   class="form-control @error('plates') is-invalid @enderror"
                                   value="{{ old('plates') }}" style="text-transform: uppercase;">
                            @error('plates')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="create_status" class="form-label fw-medium">Estatus</label>
                            <select id="create_status" name="status"
                                    class="form-select @error('status') is-invalid @enderror">
                                <option value="activo"     {{ old('status', 'activo') === 'activo'     ? 'selected' : '' }}>Activo</option>
                                <option value="vendido"    {{ old('status') === 'vendido'    ? 'selected' : '' }}>Vendido</option>
                                <option value="obsoleto"   {{ old('status') === 'obsoleto'   ? 'selected' : '' }}>Obsoleto</option>
                                <option value="reparacion" {{ old('status') === 'reparacion' ? 'selected' : '' }}>En reparación</option>
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                    </div>
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
@endcan
@endhasanyrole

{{-- ══════════════════════════════════════════════════════════
     MODAL — Importación masiva de bienes móviles
══════════════════════════════════════════════════════════════ --}}
@hasanyrole('admin|Moviles')
@can('create')
<div class="modal fade" id="modalImportAssets" tabindex="-1" aria-labelledby="modalImportAssetsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('mobile_assets.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImportAssetsLabel">
                        <i class="ri-upload-2-line me-1"></i> Importar Bienes Móviles
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-2">
                        El archivo debe incluir encabezados. Columnas sugeridas:
                    </p>
                    <ul class="text-muted fs-12 mb-3 ps-3">
                        <li>nombre_de_maquinaria o nombre</li>
                        <li>numero_economico o folio</li>
                        <li>marca, modelo, anio, serie_niv, color, operador</li>
                        <li>funcion, tipo, placas, estatus</li>
                    </ul>
                    <div class="mb-0">
                        <label for="import_mobile_assets_file" class="form-label fw-medium">
                            Archivo Excel <span class="text-danger">*</span>
                        </label>
                        <input type="file"
                               class="form-control @error('file') is-invalid @enderror"
                               id="import_mobile_assets_file" name="file"
                               accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">Formatos permitidos: .xlsx, .xls, .csv. Tamaño máximo 10 MB.</div>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnImportAssetsSubmit">
                        <i class="ri-upload-2-line me-1"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endhasanyrole

@endsection

@push('scripts')
<script>
(function () {
    // Inicializar tooltips de Bootstrap en los dots del semáforo
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    const typeSelect  = document.getElementById('create_type');
    const platesField = document.getElementById('createPlatesField');

    function togglePlates() {
        if (typeSelect && platesField) {
            platesField.style.display = typeSelect.value === 'parque_vehicular' ? '' : 'none';
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', togglePlates);
        togglePlates();
    }

    var importForm = document.querySelector('#modalImportAssets form');
    var importBtn = document.getElementById('btnImportAssetsSubmit');

    if (importForm && importBtn) {
        importForm.addEventListener('submit', function () {
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Importando...';
        });
    }

    @if ($errors->any())
    var modalId = @json($errors->has('file') ? 'modalImportAssets' : 'modalCreateAsset');
    var modalEl = document.getElementById(modalId);
    if (modalEl) {
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
    @endif
})();
</script>
@endpush
