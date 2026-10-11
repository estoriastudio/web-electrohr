@extends('layouts.app')

@section('page_title', $project->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('my-projects.index') }}">Mis proyectos</a></li>
    <li class="breadcrumb-item active">{{ $project->name }}</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-semibold mb-1">No se pudo guardar. Revisa los campos marcados.</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@php
    $engineerView = true;
    $projectStatusMap = [
        'active'   => ['label' => 'Activo',   'class' => 'bg-success-subtle text-success'],
        'inactive' => ['label' => 'Inactivo', 'class' => 'bg-warning-subtle text-warning'],
    ];
    $ps = $projectStatusMap[$project->status] ?? ['label' => $project->status, 'class' => 'bg-secondary-subtle text-secondary'];
    $workStatusMap = [
        'active'   => ['label' => 'Activa',   'class' => 'bg-success-subtle text-success'],
        'inactive' => ['label' => 'Inactiva', 'class' => 'bg-warning-subtle text-warning'],
    ];
    $solmatStatusMap = [
        'pending'           => ['label' => 'Pendiente',          'class' => 'bg-warning-subtle text-warning'],
        'sent_to_warehouse' => ['label' => 'Enviado a Almacén',  'class' => 'bg-secondary-subtle text-secondary'],
        'changes_requested' => ['label' => 'Cambios Solicitados', 'class' => 'bg-danger-subtle text-danger'],
        'linked'            => ['label' => 'Ligado',             'class' => 'bg-info-subtle text-info'],
        'completed'         => ['label' => 'Finalizado',         'class' => 'bg-success-subtle text-success'],
    ];
    $solcomStatusMap = [
        'pending'            => ['label' => 'Pendiente',          'class' => 'bg-warning-subtle text-warning'],
        'linked'             => ['label' => 'Ligado',             'class' => 'bg-info-subtle text-info'],
        'sent_to_purchasing' => ['label' => 'En Compras',         'class' => 'bg-primary-subtle text-primary'],
        'changes_requested'  => ['label' => 'Cambios Solicitados', 'class' => 'bg-danger-subtle text-danger'],
        'completed'          => ['label' => 'Finalizado',         'class' => 'bg-success-subtle text-success'],
    ];
    $ocStatusMap = [
        'emitida'    => ['label' => 'Emitida',    'class' => 'bg-info-subtle text-info'],
        'pendiente'  => ['label' => 'Pendiente',  'class' => 'bg-warning-subtle text-warning'],
        'autorizada' => ['label' => 'Autorizada', 'class' => 'bg-success-subtle text-success'],
    ];
    $deliveryMap = [
        'por_entregar' => ['label' => 'Por entregar',   'class' => 'bg-warning-subtle text-warning'],
        'parcial'      => ['label' => 'Entrega parcial', 'class' => 'bg-info-subtle text-info'],
        'entregado'    => ['label' => 'Entregado',      'class' => 'bg-success-subtle text-success'],
    ];
    $tabs = [
        'estimates' => ['label' => 'Estimaciones y Obras', 'icon' => 'ri-funds-line',          'count' => $project->estimates->count() + $project->works->count()],
        'solmat'    => ['label' => 'SOLMAT',               'icon' => 'ri-file-add-line',       'count' => $materialRequests->total()],
        'solcom'    => ['label' => 'SOLCOM',               'icon' => 'ri-shopping-cart-2-line', 'count' => $purchaseRequests->total()],
        'oc'        => ['label' => 'Órdenes de Compra',    'icon' => 'ri-file-list-3-line',    'count' => $purchaseOrders->total()],
    ];
@endphp

<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-md bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="ri-folder-3-line fs-22 text-primary"></i>
                </div>
                <div>
                    <h5 class="fw-semibold mb-0">{{ $project->name }}</h5>
                    <div class="text-muted fs-13">
                        <i class="ri-user-line me-1"></i>{{ $project->client_name }}
                        @if ($project->city || $project->state)
                            &nbsp;·&nbsp;<i class="ri-map-pin-line me-1"></i>{{ implode(', ', array_filter([$project->city, $project->state])) }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-center">
                    <div class="fw-semibold fs-18 text-primary">{{ $project->works->count() }}</div>
                    <div class="text-muted fs-12">Obras</div>
                </div>
                <div class="text-center">
                    <div class="fw-semibold fs-18 text-primary">{{ $project->currency }} {{ number_format((float) ($project->project_value ?? 0), 2) }}</div>
                    <div class="text-muted fs-12">Valor</div>
                </div>
                <span class="badge {{ $ps['class'] }} py-1 px-3 fs-12">{{ $ps['label'] }}</span>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs" id="myProjectTabs" role="tablist">
    @foreach ($tabs as $key => $tab)
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === $key ? 'active' : '' }}" id="tab-{{ $key }}-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}"
                    type="button" role="tab">
                <i class="{{ $tab['icon'] }} me-1"></i> {{ $tab['label'] }}
                <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $tab['count'] }}</span>
            </button>
        </li>
    @endforeach
</ul>

<div class="tab-content mt-0 pt-0" id="myProjectTabsContent">

    {{-- Estimaciones y obras --}}
    <div class="tab-pane fade {{ $activeTab === 'estimates' ? 'show active' : '' }}" id="tab-estimates" role="tabpanel">
        <div class="pt-3">
            @include('projects._estimates')

            <div class="card mb-3">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0"><i class="ri-building-2-line me-1 text-muted"></i> Obras asociadas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                            <thead class="bg-light-subtle">
                                <tr>
                                    <th>Obra</th>
                                    <th>Supervisor</th>
                                    <th>Residente</th>
                                    <th>Contrato</th>
                                    <th class="text-end">Valor</th>
                                    <th>Vigencia</th>
                                    <th class="text-center"># OC</th>
                                    <th>Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($project->works as $work)
                                    @php $ws = $workStatusMap[$work->status] ?? ['label' => $work->status, 'class' => 'bg-secondary-subtle text-secondary']; @endphp
                                    <tr>
                                        <td class="fw-medium text-dark">{{ $work->name }}</td>
                                        <td>{{ $work->supervisorUser?->name ?? $work->supervisor ?? '—' }}</td>
                                        <td>{{ $work->residentUser?->name ?? $work->resident ?? '—' }}</td>
                                        <td>{{ $work->contract_number ?: '—' }}</td>
                                        <td class="text-end">
                                            @if ($work->contract_value)
                                                {{ $work->currency }} {{ number_format((float) str_replace(',', '', $work->contract_value), 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-muted fs-13">
                                            {{ $work->contract_start_date ? \Carbon\Carbon::parse($work->contract_start_date)->format('d/m/Y') : '—' }}
                                            &rarr;
                                            {{ $work->contract_end_date ? \Carbon\Carbon::parse($work->contract_end_date)->format('d/m/Y') : '—' }}
                                        </td>
                                        <td class="text-center"><span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $work->purchase_orders_count }}</span></td>
                                        <td><span class="badge {{ $ws['class'] }} py-1 px-2 fs-12">{{ $ws['label'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="ri-building-2-line fs-24 d-block mb-1"></i>
                                            Este proyecto aún no tiene obras registradas.
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

    {{-- SOLMAT --}}
    <div class="tab-pane fade {{ $activeTab === 'solmat' ? 'show active' : '' }}" id="tab-solmat" role="tabpanel">
        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Solicitudes de Material</h5>
                <span class="text-muted fs-13">{{ $materialRequests->total() }} registros</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Zona</th>
                                <th>Solicitó</th>
                                <th>Fecha solicitud</th>
                                <th>Fecha requerida</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($materialRequests as $materialRequest)
                                @php $s = $solmatStatusMap[$materialRequest->status] ?? ['label' => $materialRequest->status, 'class' => 'bg-secondary-subtle text-secondary']; @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $materialRequest->folio }}</td>
                                    <td>{{ $materialRequest->zone ?: '—' }}</td>
                                    <td>{{ $materialRequest->requestedBy?->name ?? '—' }}</td>
                                    <td class="text-muted fs-13">{{ $materialRequest->request_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-muted fs-13">{{ $materialRequest->need_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td><span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span></td>
                                    <td>
                                        <a href="{{ route('material_requests.show', $materialRequest) }}" class="btn btn-light btn-sm" title="Ver detalle">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                                        Sin registros
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($materialRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">{{ $materialRequests->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>

    {{-- SOLCOM --}}
    <div class="tab-pane fade {{ $activeTab === 'solcom' ? 'show active' : '' }}" id="tab-solcom" role="tabpanel">
        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Solicitudes de Compra</h5>
                <span class="text-muted fs-13">{{ $purchaseRequests->total() }} registros</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Descripción</th>
                                <th>Zona</th>
                                <th>Fecha solicitud</th>
                                <th>Fecha requerida</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchaseRequests as $purchaseRequest)
                                @php $s = $solcomStatusMap[$purchaseRequest->status] ?? ['label' => $purchaseRequest->status, 'class' => 'bg-secondary-subtle text-secondary']; @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $purchaseRequest->folio }}</td>
                                    <td class="text-wrap" style="max-width: 320px;">{{ $purchaseRequest->short_description ?: '—' }}</td>
                                    <td>{{ $purchaseRequest->zone ?: '—' }}</td>
                                    <td class="text-muted fs-13">{{ $purchaseRequest->request_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-muted fs-13">{{ $purchaseRequest->need_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td><span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span></td>
                                    <td>
                                        <a href="{{ route('purchase_requests.show', $purchaseRequest) }}" class="btn btn-light btn-sm" title="Ver detalle">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                                        Sin registros
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($purchaseRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">{{ $purchaseRequests->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>

    {{-- Órdenes de compra --}}
    <div class="tab-pane fade {{ $activeTab === 'oc' ? 'show active' : '' }}" id="tab-oc" role="tabpanel">
        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Órdenes de Compra</h5>
                <span class="text-muted fs-13">{{ $purchaseOrders->total() }} registros</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Proveedor</th>
                                <th>Fecha</th>
                                <th>Estatus</th>
                                <th>Entrega</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchaseOrders as $purchaseOrder)
                                @php
                                    $s = $ocStatusMap[$purchaseOrder->status] ?? ['label' => $purchaseOrder->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                    $deliveryKey = $purchaseOrder->delivery_status ?: ($purchaseOrder->is_delivered ? 'entregado' : 'por_entregar');
                                    $d = $deliveryMap[$deliveryKey] ?? $deliveryMap['por_entregar'];
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $purchaseOrder->folio ?? $purchaseOrder->id }}</td>
                                    <td>{{ $purchaseOrder->supplier?->commercial_name ?: ($purchaseOrder->supplier?->rfc_name ?? '—') }}</td>
                                    <td class="text-muted fs-13">{{ $purchaseOrder->created_at?->format('d/m/Y') ?? '—' }}</td>
                                    <td><span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span></td>
                                    <td><span class="badge {{ $d['class'] }} py-1 px-2 fs-12">{{ $d['label'] }}</span></td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $purchaseOrder) }}" class="btn btn-light btn-sm" title="Ver detalle">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                                        Sin registros
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($purchaseOrders->hasPages())
                <div class="card-footer d-flex justify-content-end">{{ $purchaseOrders->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>

</div>

@endsection
