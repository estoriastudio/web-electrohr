@extends('layouts.app')

@push('styles')
<style>
    .kpi-card .card-body { padding: 1.25rem; }
</style>
@endpush

@section('page_title', $mobileAsset->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('mobile_assets.index') }}">Bienes Móviles</a></li>
    <li class="breadcrumb-item active">{{ $mobileAsset->name }}</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ── Encabezado ───────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h5 class="fw-semibold mb-1">{{ $mobileAsset->name }}</h5>
        @if ($mobileAsset->folio)
            <p class="text-muted mb-0 fs-13">Folio: {{ $mobileAsset->folio }}</p>
        @endif
    </div>
    @role('admin|orders')
    <a href="{{ route('mobile_assets.edit', $mobileAsset) }}" class="btn btn-primary btn-sm">
        <i class="ri-edit-line me-1"></i> Editar bien
    </a>
    @endrole
</div>

{{-- ══════════════════════════════════════════════════════════════
     LAYOUT — Izquierda: Historial OC  |  Derecha: Info del bien
══════════════════════════════════════════════════════════════════ --}}
<div class="row g-3">

    {{-- ── Columna izquierda — Historial de órdenes de mantenimiento ──────── --}}
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-tools-line me-1 text-muted"></i> Historial de Orden de Mantenimiento
                </h5>
                <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                    {{ $mobileAsset->maintenance_orders_count ?? $mobileAsset->maintenanceOrders->count() }}
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo de mantenimiento</th>
                                <th>Número de orden</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
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
                            @forelse ($mobileAsset->maintenanceOrders as $order)
                                @php
                                    $os = $statusMap[$order->status] ?? ['label' => $order->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                    $recurrenceLabel = $order->recurrence_type === 'recurrente' ? 'Recurrente' : 'Único';
                                @endphp
                                <tr>
                                    <td class="text-muted fs-12">
                                        {{ $order->created_at->format('d/m/Y') }}
                                    </td>
                                    <td class="fs-13">
                                        {{ $recurrenceLabel }}
                                        @if ($order->project || $order->site)
                                            <small class="text-muted d-block fs-11">
                                                {{ implode(' · ', array_filter([$order->project, $order->site])) }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}"
                                           class="fw-semibold text-dark">
                                            #{{ $order->folio }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge {{ $os['class'] }} py-1 px-2 fs-12">
                                            {{ $os['label'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}"
                                           class="btn btn-light btn-sm" title="Ver orden">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="ri-file-list-3-line fs-24 d-block mb-1 opacity-50"></i>
                                        Sin órdenes de mantenimiento registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Columna derecha — Tarjeta de información del bien ───────────────── --}}
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-information-line me-1 text-muted"></i> Información del bien
                </h5>
            </div>
            <div class="card-body">
                @php
                    $typeMap = [
                        'movil'        => ['label' => 'Móvil',        'class' => 'bg-info-subtle text-info'],
                        'maquinaria'   => ['label' => 'Maquinaria',   'class' => 'bg-primary-subtle text-primary'],
                        'equipo_menor' => ['label' => 'Equipo menor', 'class' => 'bg-secondary-subtle text-secondary'],
                    ];
                    $t = $typeMap[$mobileAsset->type] ?? null;
                @endphp
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted fw-normal fs-13">Folio</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $mobileAsset->folio ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Nombre</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $mobileAsset->name }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Marca</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $mobileAsset->brand ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Función</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $mobileAsset->asset_function ?? '—' }}</dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Tipo</dt>
                    <dd class="col-sm-7">
                        @if ($t)
                            <span class="badge {{ $t['class'] }} py-1 px-2 fs-12">{{ $t['label'] }}</span>
                        @else
                            <span class="text-muted fw-medium fs-14">—</span>
                        @endif
                    </dd>

                    @if ($mobileAsset->type === 'movil')
                    <dt class="col-sm-5 text-muted fw-normal fs-13">Placas</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $mobileAsset->plates ?? '—' }}</dd>
                    @endif

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Estatus</dt>
                    <dd class="col-sm-7">
                        @if ($mobileAsset->status === 'active')
                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activo</span>
                        @else
                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Inactivo</span>
                        @endif
                    </dd>

                    <dt class="col-sm-5 text-muted fw-normal fs-13">Registrado</dt>
                    <dd class="col-sm-7 fw-medium fs-14">{{ $mobileAsset->created_at->format('d/m/Y') }}</dd>
                </dl>
            </div>

            @role('admin|orders')
            <div class="card-footer border-top d-flex gap-2">
                <a href="{{ route('mobile_assets.edit', $mobileAsset) }}"
                   class="btn btn-primary btn-sm w-100">
                    <i class="ri-edit-line me-1"></i> Editar
                </a>
                <form action="{{ route('mobile_assets.destroy', $mobileAsset) }}"
                      method="POST"
                      onsubmit="return confirm('¿Eliminar este bien móvil? Esta acción no se puede deshacer.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </form>
            </div>
            @endrole
        </div>
    </div>

</div>

@endsection

@push('scripts')
@endpush
