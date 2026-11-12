@extends('layouts.app')

@section('page_title', 'Seguimiento de eventos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Seguimiento de eventos</li>
@endsection

@section('content')

<div class="card">
    <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="card-title mb-1"><i class="ri-route-line me-1"></i> Eventos pagados sin cerrar</h4>
            <p class="text-muted fs-13 mb-0">Hitos con pagos hechos a los que todavía les falta la entrega validada o la factura aceptada.</p>
        </div>
        <div class="btn-group btn-group-sm">
            @foreach (['all' => 'Todos', 'delivery' => 'Falta entrega', 'invoice' => 'Falta factura'] as $key => $label)
                <a href="{{ route('event_followups.index', ['missing' => $key]) }}" class="btn {{ $missing === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light-subtle">
                    <tr><th>OC / hito</th><th>Proveedor</th><th class="text-end">Pagado</th><th>Pendiente</th><th style="min-width: 320px;">Seguimiento</th></tr>
                </thead>
                <tbody>
                    @forelse ($milestones as $milestone)
                        @php
                            $order = $milestone->purchaseOrder;
                            $paid = $milestone->payments->where('status', 'pagado')->sum('amount');
                            $missingDelivery = ! $milestone->is_advance && ! $milestone->delivery_confirmed_at;
                            $missingInvoice = ! $milestone->invoices->contains('status', \App\Models\PurchaseOrderInvoice::STATUS_ACEPTADA);
                            $overdue = $milestone->followup_due_date && $milestone->followup_due_date->isPast();
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('purchase_orders.show', $order) }}" class="fw-semibold">OC #{{ $order->folio }}</a>
                                <div class="fs-12 text-muted">{{ $milestone->concept ?: 'Hito #'.$milestone->id }} · {{ $order->buyer?->name ?? 'Sin comprador' }}</div>
                            </td>
                            <td>{{ $order->supplier?->commercial_name ?: $order->supplier?->rfc_name }}</td>
                            <td class="text-end">{{ $order->currency }} {{ number_format((float) $paid, 2) }}</td>
                            <td>
                                @if ($missingDelivery)<span class="badge bg-warning-subtle text-warning">Entrega</span>@endif
                                @if ($missingInvoice)<span class="badge bg-danger-subtle text-danger">Factura</span>@endif
                            </td>
                            <td>
                                @if ($milestone->followup_note)
                                    <div class="fs-13">{{ $milestone->followup_note }}</div>
                                    <div class="fs-11 {{ $overdue ? 'text-danger' : 'text-muted' }}">
                                        @if ($milestone->followup_due_date)Compromiso {{ $milestone->followup_due_date->format('d/m/Y') }} · @endif
                                        {{ $milestone->followupUpdatedBy?->name }} · {{ $milestone->followup_updated_at?->format('d/m/Y') }}
                                    </div>
                                @endif
                                @can('event_followups.update')
                                    @if (auth()->user()->hasFullAccessScope('purchase_orders', 'update') || (int) $order->buyer_id === (int) auth()->id())
                                        <form action="{{ route('event_followups.update', $milestone) }}" method="POST" class="d-flex gap-1 mt-1">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="followup_note" class="form-control form-control-sm" placeholder="Qué falta y con quién" required maxlength="2000">
                                            <input type="date" name="followup_due_date" class="form-control form-control-sm" style="max-width: 150px;" value="{{ $milestone->followup_due_date?->toDateString() }}">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">No hay eventos pagados pendientes de cerrar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($milestones->hasPages())
        <div class="card-footer d-flex justify-content-end">{{ $milestones->links() }}</div>
    @endif
</div>

@endsection
