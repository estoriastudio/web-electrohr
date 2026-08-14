@extends('layouts.app')

@section('page_title', 'Entregas Vencidas')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_orders.index') }}">Ordenes de Compra</a></li>
    <li class="breadcrumb-item active">Entregas Vencidas</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card border-danger-subtle">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom border-danger-subtle">
                <div>
                    <h4 class="card-title mb-0 text-danger">
                        <i class="ri-alarm-warning-line me-1"></i>OCs Vencidas en Tiempos de Entrega
                    </h4>
                    <p class="text-muted fs-13 mb-0">Ordenadas por la fecha de entrega vencida mas antigua.</p>
                </div>
                <a href="{{ route('purchase_orders.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-arrow-left-line me-1"></i> Volver a ordenes
                </a>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Proveedor</th>
                                <th>Comprador asignado</th>
                                <th>Ubicacion</th>
                                <th>Fecha entrega vencida</th>
                                <th>Dias de atraso</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                                @php
                                    $oldestOverdue = $order->items
                                        ->whereNotNull('delivery_date')
                                        ->filter(function ($item) {
                                            try {
                                                return \Carbon\Carbon::parse($item->delivery_date)->lt(\Carbon\Carbon::today());
                                            } catch (\Exception $exception) {
                                                return false;
                                            }
                                        })
                                        ->sortBy('delivery_date')
                                        ->first()?->delivery_date;
                                    $supplierName = $order->supplier?->commercial_name ?: $order->supplier?->rfc_name ?: '—';
                                    $buyerName = $order->purchaseRequest?->assignedTo?->name ?? 'Sin asignar';
                                    $locationType = $order->purchaseRequest?->materialRequest?->location_type ?? 'sitio';
                                    $overdueDate = $oldestOverdue ? \Carbon\Carbon::parse($oldestOverdue) : null;
                                    $daysLate = $overdueDate ? (int) $overdueDate->diffInDays(\Carbon\Carbon::today()) : null;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">#{{ $order->folio ?? $order->id }}</td>
                                    <td>{{ $supplierName }}</td>
                                    <td>{{ $buyerName }}</td>
                                    <td>
                                        @if($locationType === 'electrohr')
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">
                                                <i class="ri-building-2-line me-1"></i>ElectroHR
                                            </span>
                                        @else
                                            <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                                <i class="ri-map-pin-line me-1"></i>En sitio
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-danger fw-medium">{{ $overdueDate?->format('d/m/Y') ?? '—' }}</td>
                                    <td>
                                        @if(!is_null($daysLate))
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">{{ $daysLate }} dias</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}" class="btn btn-light btn-sm" title="Ver OC">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-36 d-block mb-2 text-success"></i>
                                        No hay ordenes con entregas vencidas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($orders->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection