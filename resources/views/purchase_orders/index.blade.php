@extends('layouts.app')

@section('page_title', 'Órdenes de Compra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Órdenes de Compra</li>
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
                    <h4 class="card-title mb-0">Listado de órdenes de compra</h4>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalCreateOrder">
                        <i class="ri-add-line me-1"></i> Nueva orden de compra
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Tipo</th>
                                <th>Proveedor</th>
                                <th>Proyecto</th>
                                <th>Obra</th>
                                <th>Próx. Vencimiento</th>
                                <th>Moneda</th>
                                <th>Importe</th>
                                <th>Saldo cubierto</th>
                                <th>Estatus</th>
                                <th>Recurrencia</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                @php
                                    $statusMap = [
                                        'emitida'    => ['label' => 'Emitida',    'class' => 'bg-info-subtle text-info'],
                                        'pendiente'  => ['label' => 'Pendiente',  'class' => 'bg-warning-subtle text-warning'],
                                        'autorizada' => ['label' => 'Autorizada', 'class' => 'bg-success-subtle text-success'],
                                    ];
                                    $s = $statusMap[$order->status] ?? ['label' => $order->status, 'class' => 'bg-secondary-subtle text-secondary'];

                                    $tipoMap = [
                                        'materiales_servicios' => ['label' => 'Materiales / Servicios', 'class' => 'bg-primary-subtle text-primary'],
                                        'mantenimiento'        => ['label' => 'Mantenimiento',          'class' => 'bg-secondary-subtle text-secondary'],
                                    ];
                                    $t = $tipoMap[$order->type] ?? ['label' => $order->type, 'class' => 'bg-secondary-subtle text-secondary'];
                                @endphp
                                <tr>
                                    <td>
                                        <span class="badge {{ $t['class'] }} py-1 px-2 fs-12">{{ $t['label'] }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('suppliers.show', $order->supplier) }}" class="text-dark fw-medium">
                                            {{ $order->supplier->rfc_name ?? $order->supplier->commercial_name ?? '—' }}
                                        </a>
                                    </td>
                                    <td>{{ $order->project ?? '—' }}</td>
                                    <td>{{ $order->site ?? '—' }}</td>
                                    <td>{{ $order->next_due_date ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border py-1 px-2 fs-12">{{ $order->currency }}</span>
                                    </td>
                                    <td>{{ number_format($order->amount, 2) }}</td>
                                    <td>{{ number_format($order->saldo_cubierto, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                                    </td>
                                    <td>
                                        @if ($order->recurrence_type === 'recurrente')
                                            <div class="fs-12">
                                                <span class="badge bg-purple-subtle text-purple py-1 px-2 fs-12">Recurrente</span><br>
                                                <small class="text-muted">
                                                    {{ ucfirst($order->recurrence_frequency) }}
                                                    @if ($order->recurrence_start_date)
                                                        · {{ $order->recurrence_start_date->format('d/m/Y') }}
                                                    @endif
                                                    @if ($order->recurrence_end_date)
                                                        — {{ $order->recurrence_end_date->format('d/m/Y') }}
                                                    @endif
                                                </small>
                                            </div>
                                        @else
                                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">Único</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('purchase_orders.show', $order) }}"
                                               class="btn btn-light btn-sm" title="Ver detalle">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <a href="{{ route('purchase_orders.edit', $order) }}"
                                               class="btn btn-soft-primary btn-sm" title="Editar">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <form action="{{ route('purchase_orders.destroy', $order) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('¿Eliminar esta orden de compra y todos sus hitos y pagos?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">
                                        No hay órdenes de compra registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($orders->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- MODAL — Crear nueva orden de compra --}}
<div class="modal fade" id="modalCreateOrder" tabindex="-1" aria-labelledby="modalCreateOrderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('purchase_orders.store') }}" method="POST" id="formCreateOrder">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateOrderLabel">
                        <i class="ri-file-list-3-line me-1"></i> Nueva Orden de Compra
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Tipo --}}
                        <div class="col-md-6">
                            <label for="type" class="form-label fw-medium">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                <option value="">Seleccionar...</option>
                                <option value="materiales_servicios" {{ old('type') === 'materiales_servicios' ? 'selected' : '' }}>Materiales / Servicios</option>
                                <option value="mantenimiento" {{ old('type') === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Proveedor --}}
                        <div class="col-md-6">
                            <label for="supplier_id" class="form-label fw-medium">Proveedor <span class="text-danger">*</span></label>
                            <select class="form-select @error('supplier_id') is-invalid @enderror" id="supplier_id" name="supplier_id" required>
                                <option value="">Seleccionar...</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->rfc_name ?? $supplier->commercial_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Proyecto / Obra — solo visibles para materiales_servicios --}}
                        <div class="col-md-6 campo-project" style="display:none;">
                            <label for="project" class="form-label fw-medium">Proyecto</label>
                            <input type="text" class="form-control @error('project') is-invalid @enderror"
                                   id="project" name="project" value="{{ old('project') }}"
                                   placeholder="Ej. Planta Norte">
                            @error('project')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 campo-site" style="display:none;">
                            <label for="site" class="form-label fw-medium">Obra</label>
                            <input type="text" class="form-control @error('site') is-invalid @enderror"
                                   id="site" name="site" value="{{ old('site') }}"
                                   placeholder="Ej. Bodega 3">
                            @error('site')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Moneda e Importe --}}
                        <div class="col-md-4">
                            <label for="currency" class="form-label fw-medium">Moneda <span class="text-danger">*</span></label>
                            <select class="form-select @error('currency') is-invalid @enderror" id="currency" name="currency" required>
                                <option value="MXN" {{ old('currency', 'MXN') === 'MXN' ? 'selected' : '' }}>MXN — Peso Mexicano</option>
                                <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD — Dólar</option>
                                <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>EUR — Euro</option>
                            </select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="amount" class="form-label fw-medium">Importe <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   id="amount" name="amount" value="{{ old('amount', '0.00') }}" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Estatus --}}
                        <div class="col-md-4">
                            <label for="status" class="form-label fw-medium">Estatus <span class="text-danger">*</span></label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                                <option value="emitida"    {{ old('status', 'emitida') === 'emitida'    ? 'selected' : '' }}>Emitida</option>
                                <option value="pendiente"  {{ old('status') === 'pendiente'  ? 'selected' : '' }}>Pendiente</option>
                                <option value="autorizada" {{ old('status') === 'autorizada' ? 'selected' : '' }}>Autorizada</option>
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
                                           {{ old('recurrence_type', 'unico') === 'unico' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="rec_unico">Pago único</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recurrence_type"
                                           id="rec_recurrente" value="recurrente"
                                           {{ old('recurrence_type') === 'recurrente' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="rec_recurrente">Pago recurrente</label>
                                </div>
                            </div>
                        </div>

                        {{-- Campos de recurrencia (ocultos por defecto) --}}
                        <div id="campos_recurrencia" style="display:none;" class="col-12">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="recurrence_frequency" class="form-label fw-medium">Frecuencia <span class="text-danger">*</span></label>
                                    <select class="form-select @error('recurrence_frequency') is-invalid @enderror"
                                            id="recurrence_frequency" name="recurrence_frequency">
                                        <option value="">Seleccionar...</option>
                                        <option value="semanal"    {{ old('recurrence_frequency') === 'semanal'    ? 'selected' : '' }}>Semanal</option>
                                        <option value="quincenal"  {{ old('recurrence_frequency') === 'quincenal'  ? 'selected' : '' }}>Quincenal</option>
                                        <option value="mensual"    {{ old('recurrence_frequency') === 'mensual'    ? 'selected' : '' }}>Mensual</option>
                                    </select>
                                    @error('recurrence_frequency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="recurrence_start_date" class="form-label fw-medium">Fecha inicio <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('recurrence_start_date') is-invalid @enderror"
                                           id="recurrence_start_date" name="recurrence_start_date"
                                           value="{{ old('recurrence_start_date') }}">
                                    @error('recurrence_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="recurrence_end_date" class="form-label fw-medium">Fecha fin</label>
                                    <input type="date" class="form-control @error('recurrence_end_date') is-invalid @enderror"
                                           id="recurrence_end_date" name="recurrence_end_date"
                                           value="{{ old('recurrence_end_date') }}">
                                    @error('recurrence_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Crear orden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    // Toggle campos Proyecto / Obra
    function toggleProyectoObra() {
        var tipo = $('#type').val();
        if (tipo === 'materiales_servicios') {
            $('.campo-project, .campo-site').show();
        } else {
            $('.campo-project, .campo-site').hide();
            $('#project, #site').val('');
        }
    }

    // Toggle campos recurrencia
    function toggleRecurrencia() {
        if ($('input[name="recurrence_type"]:checked').val() === 'recurrente') {
            $('#campos_recurrencia').show();
        } else {
            $('#campos_recurrencia').hide();
        }
    }

    $('#type').on('change', toggleProyectoObra);
    $('input[name="recurrence_type"]').on('change', toggleRecurrencia);

    // Inicializar si hay errores de validación (old values)
    toggleProyectoObra();
    toggleRecurrencia();
});
</script>
@if ($errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = new bootstrap.Modal(document.getElementById('modalCreateOrder'));
        modal.show();
    });
</script>
@endif
@endpush
