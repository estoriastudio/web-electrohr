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

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <a href="{{ route('stocks.index') }}" class="text-muted text-decoration-none fs-13"><i class="ri-arrow-left-line me-1"></i>Volver a consulta</a>
        <h4 class="mb-1 mt-1"><span class="text-primary">{{ $concept->code }}</span> <span class="text-muted fw-normal">{{ $concept->description }}</span></h4>
        <span class="badge bg-light text-dark border py-1 px-2 fs-12">{{ $concept->unit }}</span>
        <span class="text-muted fs-13 ms-2"><i class="ri-map-pin-line me-1"></i>{{ $concept->warehouse_location }}</span>
    </div>
    <div class="d-flex gap-2"><a href="{{ route('stocks.entries.index', ['search' => $concept->code]) }}" class="btn btn-sm btn-outline-primary"><i class="ri-inbox-line me-1"></i>Ver entradas</a><a href="{{ route('stocks.exits.index', ['search' => $concept->code]) }}" class="btn btn-sm btn-outline-primary"><i class="ri-arrow-right-circle-line me-1"></i>Ver salidas</a></div>
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
@endsection
