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
                    @hasanyrole('admin|orders')
                    <a href="{{ route('purchase_orders.create') }}" class="btn btn-sm btn-primary">
                        <i class="ri-add-line me-1"></i> Nueva orden de compra
                    </a>
                    @endhasanyrole
                </div>
            </div>

            {{-- Barra de filtros --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('purchase_orders.index') }}" class="row g-2 align-items-end">
                    {{-- Búsqueda por proveedor --}}
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por proveedor…"
                                   autocomplete="off">
                        </div>
                    </div>

                    {{-- Filtro por tipo --}}
                    <div class="col-md-3">
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">Todos los tipos</option>
                            <option value="materiales_servicios" {{ $tipo === 'materiales_servicios' ? 'selected' : '' }}>Materiales / Servicios</option>
                            <option value="mantenimiento" {{ $tipo === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                        </select>
                    </div>

                    {{-- Ordenar por vencimiento --}}
                    <div class="col-md-2">
                        <select name="sort_due" class="form-select form-select-sm">
                            <option value="">Más recientes primero</option>
                            <option value="asc" {{ $sortDue === 'asc' ? 'selected' : '' }}>Vence próximo primero</option>
                            <option value="desc" {{ $sortDue === 'desc' ? 'selected' : '' }}>Vence más tarde primero</option>
                        </select>
                    </div>

                    {{-- Acciones --}}
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search || $tipo || $sortDue)
                            <a href="{{ route('purchase_orders.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Tipo</th>
                                <th>Proveedor</th>
                                <th>Proyecto / Obra</th>
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
                                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12 font-monospace">#{{ $order->folio ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $t['class'] }} py-1 px-2 fs-12">{{ $t['label'] }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('suppliers.show', $order->supplier) }}" class="text-dark fw-medium">
                                            {{ $order->supplier->rfc_name ?? $order->supplier->commercial_name ?? '—' }}
                                        </a>
                                    </td>
                                    <td style="max-width:160px">
                                        @php
                                            $proj = $order->projectRelation?->name ?? $order->project ?? null;
                                            $obra = $order->workRelation?->name  ?? $order->site    ?? null;
                                        @endphp
                                        @if ($proj)
                                            <div class="text-truncate" style="max-width:150px" title="{{ $proj }}">{{ $proj }}</div>
                                        @endif
                                        @if ($obra)
                                            <small class="text-muted text-truncate d-block" style="max-width:150px" title="{{ $obra }}">{{ $obra }}</small>
                                        @endif
                                        @if (!$proj && !$obra)—@endif
                                    </td>
                                    <td>{{ $order->next_due_date ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border py-1 px-2 fs-12">{{ $order->currency }}</span>
                                    </td>
                                    <td><i class="ri-money-dollar-circle-line me-1 text-muted"></i>{{ number_format($order->total_with_iva, 2) }}</td>
                                    <td><i class="ri-money-dollar-circle-line me-1 text-muted"></i>{{ number_format($order->saldo_cubierto, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                                    </td>
                                    <td>
                                        @if ($order->parent_id)
                                            {{-- Orden hija: mostrar origen --}}
                                            <div class="fs-12">
                                                <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">
                                                    <i class="ri-links-line me-1"></i>Serie
                                                </span>
                                                <small class="d-block text-muted mt-1">
                                                    Origen:
                                                    <a href="{{ route('purchase_orders.show', $order->parent_id) }}"
                                                       class="fw-semibold text-decoration-none">
                                                        OC #{{ $order->parent_id }}
                                                    </a>
                                                </small>
                                                @if ($order->recurrence_start_date)
                                                    <small class="text-muted">
                                                        <i class="ri-calendar-line me-1"></i>{{ $order->recurrence_start_date->format('d/m/Y') }}
                                                    </small>
                                                @endif
                                            </div>
                                        @elseif ($order->recurrence_type === 'recurrente')
                                            {{-- Orden padre recurrente --}}
                                            <div class="fs-12">
                                                <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">Recurrente</span><br>
                                                <small class="text-muted">
                                                    {{ ucfirst($order->recurrence_frequency) }}
                                                    @if ($order->recurrence_start_date)
                                                        · {{ $order->recurrence_start_date->format('d/m/Y') }}
                                                    @endif
                                                    @if ($order->recurrence_end_date)
                                                        — {{ $order->recurrence_end_date->format('d/m/Y') }}
                                                    @endif
                                                </small>
                                                @if ($order->children_count > 0)
                                                    <span class="badge bg-info-subtle text-info py-1 px-2 fs-11 d-inline-block mt-1">
                                                        <i class="ri-git-branch-line me-1"></i>{{ $order->children_count }} órdenes
                                                    </span>
                                                @endif
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
                                            <a href="{{ route('purchase_orders.pdf', $order) }}"
                                               class="btn btn-soft-secondary btn-sm" title="Descargar PDF" target="_blank">
                                                <i class="ri-file-pdf-2-line"></i>
                                            </a>
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
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
