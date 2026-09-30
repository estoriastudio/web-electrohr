@php
    $buyer = isset($purchaseOrder) ? $purchaseOrder->buyer : auth()->user();
    $buyerName = $buyer?->name ?? ($purchaseOrder->elaborated_by ?? 'Sin comprador asignado');
@endphp
<div class="col-md-4">
    <label for="buyer_name" class="form-label fw-medium">Elabora Orden</label>
    <input type="text" id="buyer_name" class="form-control bg-light-subtle" value="{{ $buyerName }}" readonly>
    <input type="hidden" name="buyer_id" value="{{ $buyer?->id }}">
    @if (isset($purchaseOrder) && !$purchaseOrder->buyer_id)
        <div class="form-text text-warning">Sin comprador asignado</div>
    @endif
</div>