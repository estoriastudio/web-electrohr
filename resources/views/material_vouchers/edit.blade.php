@extends('layouts.app')

@section('page_title', 'Editar vale ' . $materialVoucher->folio)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('material_vouchers.index') }}">Vales de Material</a></li>
    <li class="breadcrumb-item"><a href="{{ route('material_vouchers.show', $materialVoucher) }}">{{ $materialVoucher->folio }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
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

<div class="card">
    <div class="card-header border-bottom">
        <h4 class="card-title mb-0"><i class="ri-edit-line me-1"></i> Editar Vale {{ $materialVoucher->folio }}</h4>
    </div>
    <div class="card-body">
        <form action="{{ route('material_vouchers.update', $materialVoucher) }}" method="POST" class="row g-3">
            @csrf
            @method('PUT')

            <div class="col-md-6">
                <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                <select id="editSupplierId" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ old('supplier_id', $materialVoucher->supplier_id) == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->commercial_name ?: $supplier->rfc_name }}
                        </option>
                    @endforeach
                </select>
                @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha del vale <span class="text-danger">*</span></label>
                <input type="date" name="voucher_date" class="form-control @error('voucher_date') is-invalid @enderror" value="{{ old('voucher_date', optional($materialVoucher->voucher_date)->toDateString()) }}" required>
                @error('voucher_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Solicitante</label>
                <input type="text" name="requester" class="form-control @error('requester') is-invalid @enderror" value="{{ old('requester', $materialVoucher->requester) }}" placeholder="Nombre del solicitante">
                @error('requester')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Firma de autorización</label>
                <input type="text" name="authorized_signature_name" class="form-control @error('authorized_signature_name') is-invalid @enderror" value="{{ old('authorized_signature_name', $materialVoucher->authorized_signature_name) }}">
                @error('authorized_signature_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Estatus <span class="text-danger">*</span></label>
                <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="emitido" {{ old('status', $materialVoucher->status) === 'emitido' ? 'selected' : '' }}>Emitido</option>
                    <option value="autorizado" {{ old('status', $materialVoucher->status) === 'autorizado' ? 'selected' : '' }}>Autorizado</option>
                    <option value="completado" {{ old('status', $materialVoucher->status) === 'completado' ? 'selected' : '' }}>Completado</option>
                    <option value="facturado" {{ old('status', $materialVoucher->status) === 'facturado' ? 'selected' : '' }}>Facturado</option>
                    <option value="pagado" {{ old('status', $materialVoucher->status) === 'pagado' ? 'selected' : '' }}>Pagado</option>
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <input type="hidden" name="project_id" value="{{ old('project_id', $materialVoucher->project_id) }}">
            <input type="hidden" name="project_work_id" value="{{ old('project_work_id', $materialVoucher->project_work_id) }}">

            <div class="col-12 d-flex gap-2 justify-content-end">
                <a href="{{ route('material_vouchers.show', $materialVoucher) }}" class="btn btn-light">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const supplierSelect = document.getElementById('editSupplierId');

    if (supplierSelect && typeof Choices !== 'undefined') {
        new Choices(supplierSelect, {
            searchEnabled: true,
            searchPlaceholderValue: 'Buscar proveedor...',
            itemSelectText: '',
            noResultsText: 'Sin resultados',
            noChoicesText: 'Sin opciones disponibles',
        });
    }
});
</script>
@endpush
