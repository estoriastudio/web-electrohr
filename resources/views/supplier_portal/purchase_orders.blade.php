@extends('layouts.app')

@section('page_title', 'Mis Órdenes de Compra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.dashboard') }}">Portal</a></li>
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

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h4 class="card-title mb-0">Mis hitos de órdenes de compra</h4>
    </div>

    <div class="card-body border-bottom py-3">
        <form method="GET" action="{{ route('supplier_portal.purchase_orders.index') }}" class="d-flex gap-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Buscar por folio, proyecto, sitio o concepto..." autocomplete="off">
                @if ($search)
                    <a href="{{ route('supplier_portal.purchase_orders.index') }}" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                        <i class="ri-close-line"></i>
                    </a>
                @endif
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                <thead class="bg-light-subtle">
                    <tr>
                        <th class="text-center" style="width:50px;">Estado</th>
                        <th>Hito</th>
                        <th>Orden de compra</th>
                        <th>Concepto</th>
                        <th>Moneda</th>
                        <th>Importe</th>
                        <th>Vencimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($milestones as $milestone)
                        @php
                            $order = $milestone->purchaseOrder;
                            $today = \Carbon\Carbon::today();
                            $isComplete = $milestone->is_complete;
                            $hasDueDate = !is_null($milestone->due_date);
                            $isOverdue = $hasDueDate && $milestone->due_date->lt($today);
                            $isEligible = $hasDueDate && ($milestone->due_date->isPast() || $milestone->due_date->isToday());
                            $hasDocumentationReceived = $milestone->invoices->contains(function ($invoice) {
                                return !empty($invoice->file_path) && !empty($invoice->evidence_file_path);
                            });
                            $semaphoreColor = $isComplete ? '#212529' : ($isOverdue ? '#dc3545' : ($isEligible ? '#28a745' : '#ffc107'));
                        @endphp
                        <tr>
                            <td class="text-center">
                                <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:{{ $semaphoreColor }};border:2px solid rgba(0,0,0,.12);"></span>
                            </td>
                            <td class="fw-semibold">#{{ $milestone->id }}</td>
                            <td>
                                <span class="fw-medium">{{ $order->folio }}</span>
                                <small class="text-muted d-block">{{ ucfirst($order->status) }}</small>
                            </td>
                            <td>{{ $milestone->concept ?: 'Sin concepto' }}</td>
                            <td>{{ $order->currency }}</td>
                            <td>${{ number_format($milestone->effective_amount, 2) }}</td>
                            <td>{{ $milestone->due_date?->format('d/m/Y') ?? 'Sin fecha' }}</td>
                            <td>
                                @if ($hasDocumentationReceived)
                                    <span class="badge bg-success-subtle text-success py-2 px-2 fs-12">
                                        <i class="ri-checkbox-circle-line me-1"></i> Documentación recibida
                                    </span>
                                @elseif ($isEligible)
                                    <a href="{{ route('supplier_portal.invoices.create', ['purchaseOrder' => $order, 'milestone_id' => $milestone->id]) }}" class="btn btn-sm btn-primary">
                                        <i class="ri-upload-2-line me-1"></i> Subir factura
                                    </a>
                                @else
                                    <button type="button" class="btn btn-sm btn-light" disabled>
                                        <i class="ri-time-line me-1"></i> Aún no disponible
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay hitos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($milestones->hasPages())
        <div class="card-footer d-flex justify-content-end">
            {{ $milestones->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
