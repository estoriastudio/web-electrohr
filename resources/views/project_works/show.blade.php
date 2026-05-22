@extends('layouts.app')

@section('page_title', $projectWork->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyectos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.show', $projectWork->project) }}">{{ $projectWork->project->name }}</a></li>
    <li class="breadcrumb-item active">{{ $projectWork->name }}</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     ENCABEZADO
══════════════════════════════════════════════════════════════════ --}}
@php
    $wsMap = [
        'active'   => ['label' => 'Activa',   'class' => 'bg-success-subtle text-success'],
        'inactive' => ['label' => 'Inactiva', 'class' => 'bg-warning-subtle text-warning'],
    ];
    $ws = $wsMap[$projectWork->status] ?? ['label' => $projectWork->status, 'class' => 'bg-secondary-subtle text-secondary'];
@endphp

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h5 class="fw-semibold mb-0">{{ $projectWork->name }}</h5>
            <span class="badge {{ $ws['class'] }} py-1 px-2 fs-12">{{ $ws['label'] }}</span>
        </div>
        <div class="text-muted fs-13">
            <i class="ri-folder-3-line me-1"></i>
            <a href="{{ route('projects.show', $projectWork->project) }}" class="text-muted text-decoration-none">
                {{ $projectWork->project->name }}
            </a>
            &nbsp;·&nbsp;
            <i class="ri-user-line me-1"></i>{{ $projectWork->project->client_name }}
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     ÓRDENES DE COMPRA
══════════════════════════════════════════════════════════════════ --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-file-list-3-line me-1 text-muted"></i> Órdenes de compra
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th># Orden</th>
                                <th>Descripción</th>
                                <th>Hitos</th>
                                <th>Importe total</th>
                                <th>Estatus</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $statusMap = [
                                    'emitida'    => ['label' => 'Emitida',    'class' => 'bg-info-subtle text-info'],
                                    'pendiente'  => ['label' => 'Pendiente',  'class' => 'bg-warning-subtle text-warning'],
                                    'autorizada' => ['label' => 'Autorizada', 'class' => 'bg-success-subtle text-success'],
                                ];
                            @endphp
                            @forelse ($projectWork->purchaseOrders as $order)
                                @php
                                    $os = $statusMap[$order->status] ?? ['label' => $order->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                    $tipoLabel = $order->type === 'materiales_servicios' ? 'Materiales / Servicios' : 'Mantenimiento';
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}" class="fw-semibold text-dark">
                                            OC #{{ $order->id }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fs-13">{{ $tipoLabel }}</span>
                                        @if ($order->supplier)
                                            <small class="text-muted d-block fs-11">
                                                <i class="ri-building-line me-1"></i>
                                                <a href="{{ route('suppliers.show', $order->supplier) }}" class="text-muted text-decoration-none">
                                                    {{ $order->supplier->rfc_name ?? $order->supplier->commercial_name ?? '—' }}
                                                </a>
                                            </small>
                                        @endif
                                    </td>
                                    <td>{{ $order->milestones_count }}</td>
                                    <td class="fw-semibold">
                                        {{ $order->currency }} {{ number_format($order->amount, 2) }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $os['class'] }} py-1 px-2 fs-12">{{ $os['label'] }}</span>
                                    </td>
                                    <td class="text-muted fs-12">{{ $order->created_at->format('d/m/Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-file-list-3-line fs-24 d-block mb-1 opacity-50"></i>
                                        Sin órdenes de compra registradas aún.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
@endpush
