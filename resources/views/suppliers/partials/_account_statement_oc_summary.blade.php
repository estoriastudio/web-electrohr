<dl class="row g-2 mb-3">
    <dt class="col-sm-4 text-muted">Orden de Compra</dt>
    <dd class="col-sm-8">
        {{ $order->folio ?: 'OC-' . $order->id }}
        <a href="{{ route('purchase_orders.show', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm ms-2" aria-label="Abrir la orden de compra {{ $order->folio ?: 'OC-' . $order->id }} en una pestana nueva"><i class="ri-external-link-line me-1" aria-hidden="true"></i>Ver orden de compra</a>
    </dd>
    <dt class="col-sm-4 text-muted">Proveedor</dt><dd class="col-sm-8">{{ $order->supplier?->rfc_name ?: $order->supplier?->commercial_name ?: '-' }}</dd>
    <dt class="col-sm-4 text-muted">Proyecto</dt><dd class="col-sm-8">{{ $order->projectRelation?->name ?: $order->project ?: '-' }}</dd>
    <dt class="col-sm-4 text-muted">Usuario responsable</dt><dd class="col-sm-8">{{ $order->buyer?->name ?: $order->elaborated_by ?: 'No asignado' }}</dd>
    <dt class="col-sm-4 text-muted">Moneda</dt><dd class="col-sm-8">{{ $order->currency }}</dd>
</dl>
@php($reviewRows = $rows->where('status', 'conciliacion'))
@if($reviewRows->isNotEmpty())
    <div class="alert alert-warning" role="alert">
        <div class="fw-semibold mb-2"><i class="ri-search-eye-line me-1" aria-hidden="true"></i>Por que esta OC tiene registros por revisar</div>
        @foreach($reviewRows as $reviewRow)
            <div class="mb-2">
                <div class="fw-semibold fs-13">{{ $reviewRow['type'] === 'invoice' ? 'Factura ' . $reviewRow['folio'] : 'Pago #' . $reviewRow['payment_id'] . ' sin aplicar' }}</div>
                <ul class="mb-0 ps-3 fs-13">
                    @foreach($reviewRow['review_reasons'] as $reason)
                        <li>{{ $reason['message'] }} <span class="text-muted">{{ $reason['action'] }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <div class="fs-13 border-top pt-2">
            Se resuelve cuando Compras corrige el dato y guarda; la OC se reclasifica sola al volver a abrir esta pantalla. No requiere nuevos pagos ni facturas.
        </div>
    </div>
@endif
@if($economic['pending_invoice'] < 0 || $economic['pending_order'] < 0)
    <div class="alert alert-warning py-2">El importe facturado o pagado supera el total de esta OC.</div>
@endif
<h6>Resumen economico de la OC</h6>
<div class="table-responsive mb-3">
    <table class="table table-sm align-middle statement-summary">
        <tbody>
            @foreach([
                'total' => 'Importe total de la OC',
                'invoiced' => 'Importe facturado (liquido)',
                'pending_invoice' => 'Importe pendiente de facturar',
                'paid' => 'Importe pagado',
                'pending_order' => 'Importe pendiente de pagar sobre la OC',
                'pending_invoices' => 'Importe facturado sin pagar',
            ] as $field => $label)
                <tr><th class="fw-normal">{{ $label }}</th><td class="text-end fw-semibold">{{ $economic[$field] === null ? 'Por revisar' : $order->currency . ' $ ' . number_format($economic[$field] / 100, 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
<h6>Detalle por estatus de la OC</h6>
<div class="table-responsive">
    <table class="table table-sm align-middle statement-summary mb-0">
        <thead class="bg-light-subtle"><tr><th>Moneda</th><th>Estatus</th><th class="text-end">Registros</th><th class="text-end">Saldo por cubrir</th><th class="text-end">Importe cubierto</th></tr></thead>
        <tbody>
            @forelse($summary as $currency => $totals)
                @foreach($totals['statuses'] as $status => $detail)
                    <tr><td>{{ $currency }}</td><td>@include('suppliers.partials._account_statement_status', ['status' => $status])</td><td class="text-end">{{ $detail['count'] }}</td><td class="text-end">{{ in_array($status, ['pendiente', 'vencido']) ? '$ ' . number_format($detail['amount'] / 100, 2) : '-' }}</td><td class="text-end">{{ !in_array($status, ['pendiente', 'vencido']) ? '$ ' . number_format($detail['amount'] / 100, 2) : '-' }}</td></tr>
                @endforeach
            @empty
                <tr><td colspan="5" class="text-muted text-center py-3">Sin facturas aceptadas ni pagos realizados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>