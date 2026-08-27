@extends('layouts.app')

@push('styles')
<style>
    .supplier-create-section {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--bs-border-color);
    }

    .supplier-create-section-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        font-size: 1rem;
    }
</style>
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
                                <th>Portal</th>
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

                                    {{-- Portal --}}
                                    <td>
                                        @if (!$supplier->portal_user_id)
                                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Sin configurar</span>
                                        @elseif ($supplier->portal_access_enabled)
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Acceso activo</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Acceso deshabilitado</span>
                                        @endif
                                    </td>

                                    {{-- Acciones --}}
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('suppliers.show', $supplier) }}"
                                               class="btn btn-light btn-sm" title="Ver detalle">
                                                <i class="ri-eye-line"></i>
                                            </a>
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

                                            @if (!$supplier->portal_user_id)
                                                <a href="{{ route('suppliers.show', $supplier) }}?setup_portal=1"
                                                   class="btn btn-soft-success btn-sm"
                                                   title="Habilitar acceso a Portal">
                                                    <i class="ri-key-2-line"></i>
                                                </a>
                                            @elseif ($supplier->portal_access_enabled)
                                                <form action="{{ route('suppliers.portal_access.disable', $supplier) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('¿Deshabilitar acceso al Portal para este proveedor?')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-soft-danger btn-sm" title="Deshabilitar acceso a Portal">
                                                        <i class="ri-lock-line"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('suppliers.portal_access.reactivate', $supplier) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('¿Reactivar acceso al Portal para este proveedor?')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-soft-success btn-sm" title="Reactivar acceso a Portal">
                                                        <i class="ri-lock-unlock-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('suppliers.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateSupplierLabel">
                        <i class="ri-building-line me-1"></i> Nuevo proveedor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="supplier-create-section">
                                <span class="supplier-create-section-icon bg-primary-subtle text-primary">
                                    <i class="ri-building-line"></i>
                                </span>
                                <h6 class="mb-0 fw-semibold">Información general</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="create_rfc_name" class="form-label fw-medium">Razón social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('rfc_name') is-invalid @enderror"
                                   id="create_rfc_name" name="rfc_name" value="{{ old('rfc_name') }}"
                                   placeholder="Ej. Empresa Proveedora S.A. de C.V." required>
                            @error('rfc_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_commercial_name" class="form-label fw-medium">Nombre comercial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('commercial_name') is-invalid @enderror"
                                   id="create_commercial_name" name="commercial_name" value="{{ old('commercial_name') }}" required>
                            @error('commercial_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_rfc_num" class="form-label fw-medium">RFC <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('rfc_num') is-invalid @enderror"
                                   id="create_rfc_num" name="rfc_num" value="{{ old('rfc_num') }}"
                                   maxlength="13" style="text-transform:uppercase;" required>
                            @error('rfc_num')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="supplier-create-section">
                                <span class="supplier-create-section-icon bg-info-subtle text-info">
                                    <i class="ri-map-pin-line"></i>
                                </span>
                                <h6 class="mb-0 fw-semibold">Domicilio fiscal</h6>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label for="create_street" class="form-label fw-medium">Calle <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('street') is-invalid @enderror"
                                   id="create_street" name="street" value="{{ old('street') }}" required>
                            @error('street')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="create_postal_code" class="form-label fw-medium">Código Postal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('postal_code') is-invalid @enderror"
                                   id="create_postal_code" name="postal_code" value="{{ old('postal_code') }}" required>
                            @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="create_colony" class="form-label fw-medium">Colonia <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('colony') is-invalid @enderror"
                                   id="create_colony" name="colony" value="{{ old('colony') }}" required>
                            @error('colony')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="create_city" class="form-label fw-medium">Ciudad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('city') is-invalid @enderror"
                                   id="create_city" name="city" value="{{ old('city') }}" required>
                            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="create_state" class="form-label fw-medium">Estado <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('state') is-invalid @enderror"
                                   id="create_state" name="state" value="{{ old('state') }}" required>
                            @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="supplier-create-section">
                                <span class="supplier-create-section-icon bg-success-subtle text-success">
                                    <i class="ri-contacts-line"></i>
                                </span>
                                <h6 class="mb-0 fw-semibold">Contacto principal</h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="create_contact_name" class="form-label fw-medium">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('contact_name') is-invalid @enderror"
                                   id="create_contact_name" name="contact_name" value="{{ old('contact_name') }}" required>
                            @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="create_contact_phone" class="form-label fw-medium">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('contact_phone') is-invalid @enderror"
                                   id="create_contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" required>
                            @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="create_contact_email" class="form-label fw-medium">Correo electrónico <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('contact_email') is-invalid @enderror"
                                   id="create_contact_email" name="contact_email" value="{{ old('contact_email') }}" required>
                            @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="supplier-create-section">
                                <span class="supplier-create-section-icon bg-warning-subtle text-warning">
                                    <i class="ri-bank-line"></i>
                                </span>
                                <h6 class="mb-0 fw-semibold">Cuenta principal</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="create_bank_name" class="form-label fw-medium">Banco <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('bank_name') is-invalid @enderror"
                                   id="create_bank_name" name="bank_name" value="{{ old('bank_name') }}" required>
                            @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_currency" class="form-label fw-medium">Moneda <span class="text-danger">*</span></label>
                            <select class="form-select @error('currency') is-invalid @enderror" id="create_currency" name="currency" required>
                                <option value="">Seleccionar moneda...</option>
                                <option value="MXN" {{ old('currency') === 'MXN' ? 'selected' : '' }}>MXN</option>
                                <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD</option>
                                <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>EUR</option>
                            </select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_bank_account" class="form-label fw-medium">Número de cuenta <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('bank_account') is-invalid @enderror"
                                   id="create_bank_account" name="bank_account" value="{{ old('bank_account') }}"
                                   inputmode="numeric" required>
                            @error('bank_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_bank_clabe" class="form-label fw-medium">CLABE interbancaria <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('bank_clabe') is-invalid @enderror"
                                   id="create_bank_clabe" name="bank_clabe" value="{{ old('bank_clabe') }}"
                                   inputmode="numeric" maxlength="18" required>
                            @error('bank_clabe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="create_account_statement" class="form-label fw-medium">Carátula de estado de cuenta <span class="text-danger">*</span></label>
                            <input type="file" class="form-control @error('account_statement') is-invalid @enderror"
                                   id="create_account_statement" name="account_statement" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">PDF, JPG o PNG. Tamaño máximo: 10 MB.</div>
                            @error('account_statement')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
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
