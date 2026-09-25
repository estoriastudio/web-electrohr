@extends('layouts.app')

@section('page_title', 'Histórico de inventario - ' . $concept->code)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('stocks.index') }}">Inventario</a></li>
    <li class="breadcrumb-item active">{{ $concept->code }}</li>
@endsection

@section('content')
@php
    $isBelowMinimum = $concept->minimum_stock !== null && $currentStock < (float) $concept->minimum_stock;
    $isAboveMaximum = $concept->maximum_stock !== null && $currentStock > (float) $concept->maximum_stock;
@endphp

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <a href="{{ route('stocks.index') }}" class="text-muted text-decoration-none fs-13"><i class="ri-arrow-left-line me-1"></i>Volver a consulta</a>
        <h4 class="mb-1 mt-1"><span class="text-primary">{{ $concept->code }}</span> <span class="text-muted fw-normal">{{ $concept->description }}</span></h4>
        <span class="badge bg-light text-dark border py-1 px-2 fs-12">{{ $concept->unit }}</span>
        <span class="text-muted fs-13 ms-2"><i class="ri-map-pin-line me-1"></i>{{ $concept->warehouse_location }}</span>
    </div>
    <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalStockAdjustment"><i class="ri-scales-3-line me-1"></i>Ajuste manual</button><a href="{{ route('stocks.entries.index', ['search' => $concept->code]) }}" class="btn btn-sm btn-outline-primary"><i class="ri-inbox-line me-1"></i>Ver entradas</a><a href="{{ route('stocks.exits.index', ['search' => $concept->code]) }}" class="btn btn-sm btn-outline-primary"><i class="ri-arrow-right-circle-line me-1"></i>Ver salidas</a></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="avatar-md bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"><i class="ri-stack-line fs-24"></i></div><div><span class="text-muted fs-13 d-block">Stock actual</span><span class="fs-22 fw-bold {{ $isBelowMinimum ? 'text-danger' : ($isAboveMaximum ? 'text-warning' : 'text-dark') }}">{{ rtrim(rtrim(number_format($currentStock, 3, '.', ''), '0'), '.') }}</span><span class="text-muted fs-13 ms-1">{{ $concept->unit }}</span></div></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><span class="text-muted fs-13 d-block mb-1">Stock mínimo</span><span class="fs-20 fw-semibold">{{ $concept->minimum_stock ?? 'No definido' }}</span>@if($isBelowMinimum)<span class="badge bg-danger-subtle text-danger ms-2">Bajo mínimo</span>@endif</div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><span class="text-muted fs-13 d-block mb-1">Stock máximo</span><span class="fs-20 fw-semibold">{{ $concept->maximum_stock ?? 'No definido' }}</span>@if($isAboveMaximum)<span class="badge bg-warning-subtle text-warning ms-2">Sobre máximo</span>@endif</div></div></div>
</div>

<div class="card">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center"><div><h5 class="card-title mb-0"><i class="ri-history-line me-1 text-primary"></i>Histórico de movimientos</h5><span class="text-muted fs-13">Entradas y salidas de los últimos cinco años, ordenadas por fecha</span></div><span class="badge bg-light text-dark border">{{ $movements->count() }} movimiento(s)</span></div>
    <div class="card-body p-0"><div class="table-responsive overflow-visible"><table class="table align-middle table-hover table-centered mb-0"><thead class="bg-light-subtle"><tr><th>Fecha</th><th>Movimiento</th><th>Referencia</th><th class="text-end">Cantidad</th><th>Registró</th><th class="text-end">Extras</th></tr></thead><tbody>@forelse($movements as $movement)@php($isEntry = $movement->direction === 'entry')@php($record = $movement->record)<tr><td class="text-nowrap"><i class="ri-calendar-line text-muted me-1"></i>{{ $movement->date->format('d/m/Y') }}</td><td><span class="badge {{ $isEntry ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} py-1 px-2 fs-12"><i class="{{ $isEntry ? 'ri-arrow-up-line' : 'ri-arrow-down-line' }} me-1"></i>{{ $movement->label }}</span></td><td class="fw-medium">{{ $movement->reference ?: '—' }}</td><td class="text-end fw-semibold {{ $isEntry ? 'text-success' : 'text-danger' }}">{{ $isEntry ? '+' : '-' }}{{ rtrim(rtrim(number_format((float) $movement->quantity, 3, '.', ''), '0'), '.') }}</td><td class="fs-13">{{ $record->createdBy?->name ?: '—' }}</td><td class="text-end"><div class="dropdown"><button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Ver extras"><i class="ri-more-2-fill"></i></button><div class="dropdown-menu dropdown-menu-end p-3" style="min-width:280px">@if($isEntry)<p class="text-uppercase text-muted fs-11 fw-semibold mb-2">Datos de entrada</p><div class="fs-13 mb-2"><span class="text-muted">Factura:</span> @if($record->invoice_file_path)<a href="{{ route('stocks.entries.invoice.download', $record) }}" class="ms-1"><i class="ri-file-pdf-2-line text-danger me-1"></i>{{ $record->invoice_file_name ?: 'Descargar' }}</a>@else<span class="ms-1">Sin factura</span>@endif</div><div class="fs-13 mb-2"><span class="text-muted">Certificados:</span><div class="mt-1">@forelse($record->certificates as $certificate)<a href="{{ route('stocks.certificates.download', $certificate) }}" class="badge {{ $certificate->certificate_type === 'origin' ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }} text-decoration-none me-1 mb-1"><i class="ri-file-pdf-2-line me-1"></i>{{ $certificate->certificate_type === 'origin' ? 'Origen' : 'Seguridad' }}</a>@empty<span class="text-muted">Sin certificados</span>@endforelse</div></div><div class="fs-13"><span class="text-muted">Observaciones:</span><span class="d-block text-wrap">{{ $record->observations ?: '—' }}</span></div>@else<p class="text-uppercase text-muted fs-11 fw-semibold mb-2">Datos de salida</p><div class="fs-13 mb-1"><span class="text-muted">Entregado a:</span> {{ $record->recipientWorker ? trim($record->recipientWorker->first_name . ' ' . $record->recipientWorker->last_name) : ($record->recipient_name ?: '—') }}</div><div class="fs-13 mb-1"><span class="text-muted">Proyecto:</span> {{ $record->project?->name ?: '—' }}</div><div class="fs-13 mb-1"><span class="text-muted">Obra:</span> {{ $record->projectWork?->name ?: '—' }}</div><div class="fs-13 mb-1"><span class="text-muted">Estado:</span> <span class="badge {{ $record->status === 'completed' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $record->status === 'completed' ? 'Completada' : $record->status }}</span></div><div class="fs-13"><span class="text-muted">Observaciones:</span><span class="d-block text-wrap">{{ $record->observations ?: '—' }}</span></div>@endif</div></div></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4"><i class="ri-history-line fs-24 d-block mb-1 opacity-50"></i>Este suministro no tiene movimientos en los últimos cinco años.</td></tr>@endforelse</tbody></table></div></div>
</div>

<div class="modal fade" id="modalStockAdjustment" tabindex="-1" aria-labelledby="modalStockAdjustmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('stocks.adjust', $concept) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalStockAdjustmentLabel"><i class="ri-scales-3-line me-1"></i>Ajuste manual de stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13">{{ $concept->code }}: stock actual <span class="fw-semibold">{{ rtrim(rtrim(number_format($currentStock, 3, '.', ''), '0'), '.') }} {{ $concept->unit }}</span></p>
                    <div class="row g-3">
                        <div class="col-md-6"><label for="adjustment_direction" class="form-label fw-medium">Operación <span class="text-danger">*</span></label><select class="form-select @error('direction') is-invalid @enderror" id="adjustment_direction" name="direction" required><option value="increase" @selected(old('direction') === 'increase')>Aumentar stock</option><option value="decrease" @selected(old('direction') === 'decrease')>Disminuir stock</option></select>@error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="adjustment_quantity" class="form-label fw-medium">Cantidad <span class="text-danger">*</span></label><div class="input-group"><input type="number" class="form-control @error('quantity') is-invalid @enderror" id="adjustment_quantity" name="quantity" value="{{ old('quantity') }}" min="0.001" step="0.001" required><span class="input-group-text">{{ $concept->unit }}</span>@error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                        <div class="col-12"><label for="adjustment_date" class="form-label fw-medium">Fecha <span class="text-danger">*</span></label><input type="date" class="form-control @error('adjusted_at') is-invalid @enderror" id="adjustment_date" name="adjusted_at" value="{{ old('adjusted_at', today()->toDateString()) }}" required>@error('adjusted_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label for="adjustment_observations" class="form-label fw-medium">Motivo del ajuste <span class="text-danger">*</span></label><textarea class="form-control @error('observations') is-invalid @enderror" id="adjustment_observations" name="observations" rows="3" maxlength="2000" required>{{ old('observations') }}</textarea>@error('observations')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning"><i class="ri-save-line me-1"></i>Registrar ajuste</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
