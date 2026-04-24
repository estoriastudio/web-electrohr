@extends('layouts.app')

@section('page_title', 'Editar Orden de Compra #' . $purchaseOrder->id)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_orders.index') }}">Órdenes de Compra</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_orders.show', $purchaseOrder) }}">OC #{{ $purchaseOrder->id }}</a></li>
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

<form action="{{ route('purchase_orders.update', $purchaseOrder) }}" method="POST" id="formEditOrder">
    @csrf
    @method('PUT')

    <div class="row g-3">

        {{-- ── CARD PRINCIPAL ── --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <h4 class="card-title mb-0">Información de la Orden de Compra</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        {{-- Tipo --}}
                        <div class="col-md-6">
                            <label for="type" class="form-label fw-medium">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="materiales_servicios" {{ old('type', $purchaseOrder->type) === 'materiales_servicios' ? 'selected' : '' }}>Materiales / Servicios</option>
                                <option value="mantenimiento"        {{ old('type', $purchaseOrder->type) === 'mantenimiento'        ? 'selected' : '' }}>Mantenimiento</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Proveedor --}}
                        <div class="col-md-6">
                            <label for="supplier_id" class="form-label fw-medium">Proveedor <span class="text-danger">*</span></label>
                            <select class="form-select @error('supplier_id') is-invalid @enderror" id="supplier_id" name="supplier_id" required>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ old('supplier_id', $purchaseOrder->supplier_id) == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->rfc_name ?? $supplier->commercial_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Proyecto / Obra --}}
                        <div class="col-md-6 campo-project">
                            <label for="project" class="form-label fw-medium">Proyecto</label>
                            <input type="text" class="form-control @error('project') is-invalid @enderror"
                                   id="project" name="project"
                                   value="{{ old('project', $purchaseOrder->project) }}"
                                   placeholder="Ej. Planta Norte">
                            @error('project')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 campo-site">
                            <label for="site" class="form-label fw-medium">Obra</label>
                            <input type="text" class="form-control @error('site') is-invalid @enderror"
                                   id="site" name="site"
                                   value="{{ old('site', $purchaseOrder->site) }}"
                                   placeholder="Ej. Bodega 3">
                            @error('site')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Moneda --}}
                        <div class="col-md-4">
                            <label for="currency" class="form-label fw-medium">Moneda <span class="text-danger">*</span></label>
                            <select class="form-select @error('currency') is-invalid @enderror" id="currency" name="currency" required>
                                <option value="MXN" {{ old('currency', $purchaseOrder->currency) === 'MXN' ? 'selected' : '' }}>MXN — Peso Mexicano</option>
                                <option value="USD" {{ old('currency', $purchaseOrder->currency) === 'USD' ? 'selected' : '' }}>USD — Dólar</option>
                                <option value="EUR" {{ old('currency', $purchaseOrder->currency) === 'EUR' ? 'selected' : '' }}>EUR — Euro</option>
                            </select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Importe --}}
                        <div class="col-md-4">
                            <label for="amount" class="form-label fw-medium">Importe <span class="text-danger">*</span></label>
                            @php $hasMillestones = $purchaseOrder->milestones()->count() > 0; @endphp
                            <input type="number" step="0.01" min="0"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   id="amount" name="amount"
                                   value="{{ old('amount', $purchaseOrder->amount) }}"
                                   {{ $hasMillestones ? 'readonly' : '' }} required>
                            @if ($hasMillestones)
                                <div class="form-text text-warning">
                                    <i class="ri-lock-line me-1"></i>El importe no puede modificarse porque la OC ya tiene hitos configurados.
                                </div>
                            @endif
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Estatus --}}
                        <div class="col-md-4">
                            <label for="status" class="form-label fw-medium">Estatus <span class="text-danger">*</span></label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                                <option value="emitida"    {{ old('status', $purchaseOrder->status) === 'emitida'    ? 'selected' : '' }}>Emitida</option>
                                <option value="pendiente"  {{ old('status', $purchaseOrder->status) === 'pendiente'  ? 'selected' : '' }}>Pendiente</option>
                                <option value="autorizada" {{ old('status', $purchaseOrder->status) === 'autorizada' ? 'selected' : '' }}>Autorizada</option>
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Recurrencia --}}
                        <div class="col-12">
                            <label class="form-label fw-medium">Recurrencia <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recurrence_type"
                                           id="rec_unico" value="unico"
                                           {{ old('recurrence_type', $purchaseOrder->recurrence_type) === 'unico' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="rec_unico">Pago único</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recurrence_type"
                                           id="rec_recurrente" value="recurrente"
                                           {{ old('recurrence_type', $purchaseOrder->recurrence_type) === 'recurrente' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="rec_recurrente">Pago recurrente</label>
                                </div>
                            </div>
                        </div>

                        {{-- Campos de recurrencia --}}
                        <div id="campos_recurrencia" class="col-12">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="recurrence_frequency" class="form-label fw-medium">Frecuencia <span class="text-danger">*</span></label>
                                    <select class="form-select @error('recurrence_frequency') is-invalid @enderror"
                                            id="recurrence_frequency" name="recurrence_frequency">
                                        <option value="">Seleccionar...</option>
                                        <option value="semanal"   {{ old('recurrence_frequency', $purchaseOrder->recurrence_frequency) === 'semanal'   ? 'selected' : '' }}>Semanal</option>
                                        <option value="quincenal" {{ old('recurrence_frequency', $purchaseOrder->recurrence_frequency) === 'quincenal' ? 'selected' : '' }}>Quincenal</option>
                                        <option value="mensual"   {{ old('recurrence_frequency', $purchaseOrder->recurrence_frequency) === 'mensual'   ? 'selected' : '' }}>Mensual</option>
                                    </select>
                                    @error('recurrence_frequency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="recurrence_start_date" class="form-label fw-medium">Fecha inicio <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('recurrence_start_date') is-invalid @enderror"
                                           id="recurrence_start_date" name="recurrence_start_date"
                                           value="{{ old('recurrence_start_date', $purchaseOrder->recurrence_start_date?->format('Y-m-d')) }}">
                                    @error('recurrence_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="recurrence_end_date" class="form-label fw-medium">Fecha fin</label>
                                    <input type="date" class="form-control @error('recurrence_end_date') is-invalid @enderror"
                                           id="recurrence_end_date" name="recurrence_end_date"
                                           value="{{ old('recurrence_end_date', $purchaseOrder->recurrence_end_date?->format('Y-m-d')) }}">
                                    @error('recurrence_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('purchase_orders.show', $purchaseOrder) }}" class="btn btn-light">
                        <i class="ri-arrow-left-line me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Guardar cambios
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>

@endsection

@push('scripts')
<script>
$(function () {
    function toggleProyectoObra() {
        if ($('#type').val() === 'materiales_servicios') {
            $('.campo-project, .campo-site').show();
        } else {
            $('.campo-project, .campo-site').hide();
        }
    }

    function toggleRecurrencia() {
        if ($('input[name="recurrence_type"]:checked').val() === 'recurrente') {
            $('#campos_recurrencia').show();
        } else {
            $('#campos_recurrencia').hide();
        }
    }

    $('#type').on('change', toggleProyectoObra);
    $('input[name="recurrence_type"]').on('change', toggleRecurrencia);

    toggleProyectoObra();
    toggleRecurrencia();
});
</script>
@endpush
