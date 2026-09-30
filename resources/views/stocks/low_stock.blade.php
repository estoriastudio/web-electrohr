@extends('layouts.app')

@section('page_title', 'Reporte de inventario bajo mínimo')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('stocks.index') }}">Consulta de inventario</a></li>
    <li class="breadcrumb-item active">Reporte bajo mínimo</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <h4 class="card-title mb-0"><i class="ri-stock-line text-danger me-1"></i>Conceptos bajo el stock mínimo <span class="badge bg-danger-subtle text-danger ms-1">{{ $concepts->total() }}</span></h4>
        <a href="{{ route('stocks.index') }}" class="btn btn-sm btn-outline-primary"><i class="ri-arrow-left-line me-1"></i>Volver a consulta</a>
    </div>
    <div class="card-body border-bottom py-3">
        <form method="GET" action="{{ route('stocks.low_stock') }}" class="row g-2 align-items-end">
            <div class="col-md-8 col-xl-6">
                <label for="low-stock-search" class="form-label fs-12 mb-1">Concepto o ubicación</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                    <input type="search" id="low-stock-search" name="search" value="{{ $search }}" class="form-control" placeholder="Código, descripción o ubicación" autocomplete="off">
                </div>
            </div>
            <div class="col-md-4 col-xl-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="ri-filter-3-line me-1"></i>Filtrar</button>
                @if($search)
                    <a href="{{ route('stocks.low_stock') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar búsqueda"><i class="ri-close-line"></i></a>
                @endif
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                <thead class="bg-light-subtle">
                    <tr><th>Prioridad</th><th>Código</th><th>Descripción</th><th>Unidad</th><th>Ubicación</th><th class="text-end">Stock actual</th><th class="text-end">Mínimo</th><th class="text-end">Faltante al mínimo</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($concepts as $concept)
                        <tr>
                            <td>
                                <span class="badge {{ $concept->priority === 'high' ? 'bg-danger-subtle text-danger' : ($concept->priority === 'low' ? 'bg-info-subtle text-info' : 'bg-warning-subtle text-warning') }} py-1 px-2 fs-12">
                                    <i class="ri-flag-line me-1"></i>{{ $concept->priority === 'high' ? 'Alta' : ($concept->priority === 'low' ? 'Baja' : 'Media') }}
                                </span>
                            </td>
                            <td><a href="{{ route('stocks.show', $concept) }}" class="fw-semibold text-primary text-decoration-none">{{ $concept->code }}</a></td>
                            <td><span class="hover-marquee d-block" style="--marquee-width:340px" title="{{ $concept->description }}"><span class="track"><span>{{ $concept->description }}</span><span aria-hidden="true">{{ $concept->description }}</span></span></span></td>
                            <td>{{ $concept->unit }}</td>
                            <td><i class="ri-map-pin-line text-muted me-1"></i>{{ $concept->warehouse_location }}</td>
                            <td class="text-end fw-bold text-danger">{{ number_format((float) $concept->current_stock, 3) }}</td>
                            <td class="text-end">{{ number_format((float) $concept->minimum_stock, 3) }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $concept->minimum_stock - (float) $concept->current_stock, 3) }}</td>
                            <td><span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12"><i class="ri-alarm-warning-line me-1"></i>Bajo mínimo</span></td>
                            <td class="text-end"><a href="{{ route('stocks.show', $concept) }}" class="btn btn-light btn-sm" title="Ver histórico"><i class="ri-history-line"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4"><i class="ri-checkbox-circle-line fs-24 d-block mb-1 opacity-50"></i>No hay conceptos por debajo del mínimo con los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($concepts->hasPages())
        <div class="card-footer d-flex justify-content-end">{{ $concepts->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection