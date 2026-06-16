@extends('layouts.app')

@push('styles')
@endpush

@section('page_title', 'Proveedores')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Proveedores</li>
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

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Listado de proveedores</h4>
                </div>
                @hasanyrole('admin|Orden de compra')
                @can('create')
                <div class="d-flex gap-2">
                    {{-- Importar --}}
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#modalImport">
                        <i class="ri-upload-2-line me-1"></i> Importar
                    </button>

                    {{-- Exportar --}}
                    <a href="{{ route('suppliers.export') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-download-2-line me-1"></i> Exportar
                    </a>

                    {{-- Crear nuevo proveedor --}}
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalCreateSupplier">
                        <i class="ri-add-line me-1"></i> Crear nuevo proveedor
                    </button>
                </div>
                @endcan
                @endhasanyrole
            </div>

            {{-- Barra de búsqueda --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('suppliers.index') }}" class="d-flex gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}"
                               class="form-control"
                               placeholder="Buscar por razón social o nombre comercial…"
                               autocomplete="off">
                        @if ($search)
                            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Proveedor</th>
                                <th># Órdenes de compra</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($suppliers as $supplier)
                                <tr>
                                    {{-- Nombre --}}
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                                <span class="text-primary fw-semibold">
                                                    {{ strtoupper(substr($supplier->rfc_name ?? $supplier->commercial_name ?? '?', 0, 1)) }}
                                                </span>
                                            </div>
                                            <div>
                                                <a href="{{ route('suppliers.show', $supplier) }}"
                                                   class="text-dark fw-medium fs-15">
                                                    {{ $supplier->rfc_name ?? $supplier->commercial_name ?? '—' }}
                                                </a>
                                                @if ($supplier->commercial_name && $supplier->rfc_name)
                                                    <div class="text-muted fs-12">{{ $supplier->commercial_name }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- # Órdenes --}}
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                            {{ $supplier->purchase_orders_count }}
                                        </span>
                                    </td>

                                    {{-- Estatus --}}
                                    <td>
                                        @php
                                            $statusMap = [
                                                'active'      => ['label' => 'Activo',    'class' => 'bg-success-subtle text-success'],
                                                'inactive'    => ['label' => 'Inactivo',  'class' => 'bg-warning-subtle text-warning'],
                                                'blacklisted' => ['label' => 'Bloqueado', 'class' => 'bg-danger-subtle text-danger'],
                                            ];
                                            $s = $statusMap[$supplier->status] ?? ['label' => 'Sin definir', 'class' => 'bg-secondary-subtle text-secondary'];
                                        @endphp
                                        <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                                    </td>

                                    {{-- Acciones --}}
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('suppliers.show', $supplier) }}"
                                               class="btn btn-light btn-sm" title="Ver detalle">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @can('update')
                                                <a href="{{ route('suppliers.edit', $supplier) }}"
                                                   class="btn btn-soft-primary btn-sm" title="Editar">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            @endcan
                                            @can('delete')
                                                <form action="{{ route('suppliers.destroy', $supplier) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('¿Eliminar este proveedor?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No hay proveedores registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($suppliers->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $suppliers->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Crear nuevo proveedor
══════════════════════════════════════════════════════════════════ --}}
@hasanyrole('admin|Orden de compra')
@can('create')
<div class="modal fade" id="modalCreateSupplier" tabindex="-1" aria-labelledby="modalCreateSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('suppliers.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateSupplierLabel">
                        <i class="ri-building-line me-1"></i> Nuevo proveedor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        Ingresa los datos básicos. Podrás completar el perfil del proveedor desde su vista de detalle.
                    </p>

                    <div class="mb-3">
                        <label for="rfc_name" class="form-label fw-medium">
                            Razón social / Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control @error('rfc_name') is-invalid @enderror"
                               id="rfc_name" name="rfc_name"
                               value="{{ old('rfc_name') }}"
                               placeholder="Ej. Empresa Proveedora S.A. de C.V."
                               required>
                        @error('rfc_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contact_name" class="form-label fw-medium">Nombre del contacto</label>
                        <input type="text" class="form-control @error('contact_name') is-invalid @enderror"
                               id="contact_name" name="contact_name"
                               value="{{ old('contact_name') }}"
                               placeholder="Ej. Juan Pérez">
                        <div class="form-text">Se registrará como contacto principal del proveedor.</div>
                        @error('contact_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-medium">Correo electrónico</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                               id="email" name="email"
                               value="{{ old('email') }}"
                               placeholder="contacto@proveedor.com">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label for="phone" class="form-label fw-medium">Teléfono</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror"
                               id="phone" name="phone"
                               value="{{ old('phone') }}"
                               placeholder="Ej. 55 1234 5678">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Crear proveedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endhasanyrole


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Importar proveedores desde Excel
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalImport" tabindex="-1" aria-labelledby="modalImportLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('suppliers.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImportLabel">
                        <i class="ri-upload-2-line me-1"></i> Importar proveedores
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        Selecciona un archivo Excel (<code>.xlsx</code> o <code>.xls</code>) con los datos de los proveedores.
                    </p>
                    <div class="mb-0">
                        <label for="import_file" class="form-label fw-medium">
                            Archivo <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control @error('file') is-invalid @enderror"
                               id="import_file" name="file"
                               accept=".xlsx,.xls,.csv" required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnImportSubmit">
                        <i class="ri-upload-2-line me-1"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
@if ($errors->any())
<script>
    // Re-abrir el modal de creación si hubo errores de validación
    document.addEventListener('DOMContentLoaded', function () {
        var modal = new bootstrap.Modal(document.getElementById('modalCreateSupplier'));
        modal.show();
    });
</script>
@endif
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const importForm = document.querySelector('#modalImport form');
        const importBtn  = document.getElementById('btnImportSubmit');

        importForm.addEventListener('submit', function () {
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Importando...';
        });
    });
</script>
@endpush
