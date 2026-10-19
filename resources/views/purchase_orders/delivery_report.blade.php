@extends('layouts.app')

@section('page_title', 'Órdenes de Compra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Órdenes de Compra</li>
@endsection

@php
    $fmtQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    $deliveryBadgeMap = [
        'por_entregar' => ['label' => 'Por entregar', 'class' => 'bg-warning-subtle text-warning'],
        'parcial' => ['label' => 'Entrega parcial', 'class' => 'bg-info-subtle text-info'],
        'entregado' => ['label' => 'Entregado', 'class' => 'bg-success-subtle text-success'],
    ];
    $canReport = fn ($order) => $order->status === 'autorizada'
        && $order->closed_at === null
        && $order->delivery_status !== 'entregado';
@endphp

@section('content')
<div class="row">
    <div class="col-xl-12">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="ri-search-line me-1"></i>Buscar Orden de Compra por Folio</h4>
                <p class="text-muted fs-13 mb-0">Busca la OC y reporta la entrega de sus conceptos con la evidencia correspondiente.</p>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('purchase_orders.delivery_report') }}" class="row g-2">
                    <div class="col-md-6 col-lg-4">
                        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Folio de la OC" autofocus required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i>Buscar</button>
                        @if ($search !== '')
                            <a href="{{ route('purchase_orders.delivery_report') }}" class="btn btn-outline-secondary">Limpiar</a>
                        @endif
                    </div>
                </form>
            </div>

            @if ($search !== '')
                <div class="card-body border-top p-0">
                    <div class="table-responsive">
                        <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                            <thead class="bg-light-subtle">
                                <tr>
                                    <th>Folio</th>
                                    <th>Proveedor</th>
                                    <th>Tipo</th>
                                    <th>Estatus OC</th>
                                    <th>Entrega</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($orders as $order)
                                    @php
                                        $rowDelivery = $deliveryBadgeMap[$order->delivery_status ?: 'por_entregar'] ?? $deliveryBadgeMap['por_entregar'];
                                        $isSelected = $selectedOrder && $selectedOrder->id === $order->id;
                                    @endphp
                                    <tr class="{{ $isSelected ? 'table-active' : '' }}">
                                        <td class="fw-semibold">#{{ $order->folio ?? $order->id }}</td>
                                        <td>{{ $order->supplier?->commercial_name ?: $order->supplier?->rfc_name ?: '—' }}</td>
                                        <td class="text-capitalize">{{ $order->type ?: '—' }}</td>
                                        <td class="text-capitalize">{{ str_replace('_', ' ', $order->status) }}{{ $order->closed_at ? ' (cerrada)' : '' }}</td>
                                        <td><span class="badge {{ $rowDelivery['class'] }} py-1 px-2 fs-12">{{ $rowDelivery['label'] }}</span></td>
                                        <td class="text-end">
                                            @if ($isSelected)
                                                <span class="text-muted fs-12">Seleccionada</span>
                                            @elseif ($canReport($order))
                                                <a href="{{ route('purchase_orders.delivery_report', ['search' => $search, 'order' => $order->id]) }}" class="btn btn-sm btn-success">
                                                    <i class="ri-truck-line me-1"></i>Reportar entrega
                                                </a>
                                            @else
                                                <span class="text-muted fs-12">
                                                    {{ $order->delivery_status === 'entregado' ? 'Entrega completa' : 'No admite entregas' }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron órdenes de compra con el folio "{{ $search }}".</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        @if ($selectedOrder)
            @php
                $order = $selectedOrder;
                $orderDelivery = $deliveryBadgeMap[$order->delivery_status ?: 'por_entregar'] ?? $deliveryBadgeMap['por_entregar'];
                $reportable = $canReport($order);
                $milestones = $order->milestones->sortBy('id')->values();
                $deliveryHistory = $order->items
                    ->flatMap(fn ($i) => $i->deliveries->each->setRelation('item', $i))
                    ->sortByDesc('delivered_at')
                    ->values();
            @endphp
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-0"><i class="ri-truck-line me-1 text-success"></i>OC #{{ $order->folio ?? $order->id }}</h4>
                        <p class="text-muted fs-13 mb-0">{{ $order->supplier?->commercial_name ?: $order->supplier?->rfc_name ?: '—' }}</p>
                    </div>
                    <div class="text-end">
                        <span class="badge {{ $orderDelivery['class'] }} py-1 px-2 fs-12">{{ $orderDelivery['label'] }}</span>
                        <div class="text-muted fs-12 mt-1">{{ $order->delivery_progress }}% entregado</div>
                    </div>
                </div>

                <form action="{{ route('purchase_orders.deliveries.store', $order) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="return_to" value="delivery_report">
                    <div class="card-body">
                        @if (! $reportable)
                            <div class="alert alert-warning py-2 fs-13">
                                Esta OC no admite reportar entregas (debe estar autorizada, abierta y con entregas pendientes).
                            </div>
                        @else
                            <p class="text-muted fs-13">Selecciona los conceptos que se están entregando, indica la cantidad y adjunta la evidencia.</p>
                        @endif

                        @if ($errors->has('item_ids') || $errors->has('item_ids.*'))
                            <div class="alert alert-danger py-2 fs-13">{{ $errors->first('item_ids') ?: $errors->first('item_ids.*') }}</div>
                        @endif

                        @if ($order->items->isEmpty())
                            <div class="text-muted text-center py-3">Esta OC no tiene conceptos.</div>
                        @else
                            <div class="table-responsive border rounded mb-3">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th style="width:40px;">
                                                <input type="checkbox" class="form-check-input" id="delivery_select_all" title="Seleccionar todos"
                                                       @disabled(! $reportable || $order->items->whereNull('delivered_at')->isEmpty())>
                                            </th>
                                            <th>Concepto</th>
                                            <th>Unidad</th>
                                            <th class="text-end">Pedido</th>
                                            <th class="text-end">Entregado</th>
                                            <th class="text-end" style="width:140px;">A entregar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($order->items as $item)
                                            @php
                                                $isItemDelivered = $item->delivered_at !== null;
                                                $oldQuantity = old('quantities.' . $item->id);
                                            @endphp
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input delivery-item-checkbox"
                                                           name="item_ids[]" value="{{ $item->id }}" id="delivery_item_{{ $item->id }}"
                                                           {{ $isItemDelivered ? 'checked disabled' : '' }}
                                                           {{ ! $reportable && ! $isItemDelivered ? 'disabled' : '' }}
                                                           {{ ! $isItemDelivered && in_array($item->id, (array) old('item_ids', [])) ? 'checked' : '' }}>
                                                </td>
                                                <td><label for="delivery_item_{{ $item->id }}" class="mb-0">{{ $item->description }}</label></td>
                                                <td class="fs-12">{{ $item->unit }}</td>
                                                <td class="text-end">{{ $fmtQty($item->quantity) }}</td>
                                                <td class="text-end {{ $isItemDelivered ? 'text-success' : '' }}">{{ $fmtQty($item->delivered_quantity) }}</td>
                                                <td class="text-end">
                                                    @if ($isItemDelivered)
                                                        <span class="text-success fs-12"><i class="ri-checkbox-circle-line me-1"></i>Completo</span>
                                                    @else
                                                        <input type="number" step="0.0001" min="0.0001" max="{{ $item->pending_quantity }}"
                                                               class="form-control form-control-sm text-end delivery-qty-input @error('quantities.' . $item->id) is-invalid @enderror"
                                                               name="quantities[{{ $item->id }}]" data-item-id="{{ $item->id }}"
                                                               value="{{ $oldQuantity ?? $fmtQty($item->pending_quantity) }}"
                                                               data-pending="{{ $item->pending_quantity }}" disabled>
                                                        @error('quantities.' . $item->id)<div class="invalid-feedback d-block text-start">{{ $message }}</div>@enderror
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($reportable)
                                @if ($milestones->isNotEmpty())
                                    <label for="delivery_milestone_id" class="form-label fw-medium">Hito que cubre esta entrega</label>
                                    <select id="delivery_milestone_id" name="milestone_id" class="form-select mb-3 @error('milestone_id') is-invalid @enderror">
                                        <option value="">Sin hito</option>
                                        @foreach ($milestones as $index => $milestone)
                                            <option value="{{ $milestone->id }}" @selected((string) old('milestone_id') === (string) $milestone->id)>
                                                Hito #{{ $index + 1 }}{{ $milestone->concept ? ' · ' . $milestone->concept : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('milestone_id')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                                @endif

                                <label for="delivery_evidence" class="form-label fw-medium">Evidencia <span class="text-danger">*</span></label>
                                <input id="delivery_evidence" name="delivery_evidence" type="file" required
                                       class="form-control @error('delivery_evidence') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                <div class="form-text">PDF, JPG, PNG o WEBP. Máximo 10 MB.</div>
                                @error('delivery_evidence')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @endif
                        @endif

                        @if ($deliveryHistory->isNotEmpty())
                            <hr>
                            <h6 class="fw-semibold fs-13 mb-2"><i class="ri-history-line me-1"></i>Historial de entregas</h6>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm align-middle mb-0 fs-12">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Concepto</th>
                                            <th class="text-end">Cantidad</th>
                                            <th>Registró</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($deliveryHistory as $delivery)
                                            <tr>
                                                <td>{{ $delivery->delivered_at?->format('d/m/Y H:i') }}</td>
                                                <td>{{ $delivery->item->description }}</td>
                                                <td class="text-end">{{ $fmtQty($delivery->quantity) }} {{ $delivery->item->unit }}</td>
                                                <td>{{ $delivery->deliveredByUser?->name ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if ($reportable && $order->items->isNotEmpty())
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-success"><i class="ri-check-line me-1"></i>Registrar entrega</button>
                        </div>
                    @endif
                </form>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var selectAll = document.getElementById('delivery_select_all');
    var boxes = Array.prototype.slice.call(document.querySelectorAll('.delivery-item-checkbox:not(:disabled)'));

    if (!selectAll || !boxes.length) return;

    function syncRow(box) {
        var input = document.querySelector('.delivery-qty-input[data-item-id="' + box.value + '"]');
        if (input) input.disabled = !box.checked;
    }

    function syncSelectAll() {
        selectAll.checked = boxes.every(function (b) { return b.checked; });
    }

    selectAll.addEventListener('change', function () {
        boxes.forEach(function (b) { b.checked = selectAll.checked; syncRow(b); });
    });
    boxes.forEach(function (b) {
        b.addEventListener('change', function () { syncRow(b); syncSelectAll(); });
        syncRow(b);
    });
    syncSelectAll();
})();
</script>
@endpush
