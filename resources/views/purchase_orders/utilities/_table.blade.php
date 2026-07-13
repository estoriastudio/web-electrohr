{{--
    Partial unificado para las tres vistas de órdenes de compra.
    El modo se detecta automáticamente desde la ruta activa:
      - 'index'    → purchase_orders.index
      - 'archived' → purchase_orders.archived
      - 'trashed'  → purchase_orders.soft_deleted
--}}
@php
    $mode = request()->routeIs('purchase_orders.archived')
        ? 'archived'
        : (request()->routeIs('purchase_orders.soft_deleted') ? 'trashed' : 'index');

    // Número de columnas para el colspan del empty-state
    $colspan = 9 + ($mode !== 'index' ? 1 : 0);
@endphp

<div class="table-responsive po-orders-table-responsive">
    <table class="table align-middle table-hover table-centered mb-0 app-list-table">
        <thead class="bg-light-subtle">
            <tr>
                <th>Acciones</th>
                <th>Trazabilidad</th>
                <th>Folio</th>
                <th>Proveedor</th>
                <th>Proyecto / Obra</th>
                <th>Próx. Vencimiento</th>
                <th>Importe</th>
                <th>Saldo cubierto</th>
                <th>Estatus</th>
                @if ($mode === 'archived')
                    <th>Archivada el</th>
                @elseif ($mode === 'trashed')
                    <th>Eliminada el</th>
                @endif
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
                    $supplierName = $order->supplier->rfc_name ?? $order->supplier->commercial_name ?? '—';
                    $supplierNameShort = \Illuminate\Support\Str::limit($supplierName, 50, '...');
                    $solcom = $order->purchaseRequest ?? null;
                    $solmat = $solcom?->materialRequest ?? null;
                    $currencyCode = strtoupper($order->currency ?? '');
                    $currencySymbol = match ($currencyCode) {
                        'MXN' => '$',
                        'USD' => 'US$',
                        'EUR' => '€',
                        default => '$',
                    };

                @endphp
                <tr @if ($mode === 'trashed') class="table-danger" @endif>
                    {{-- Menú de acciones --}}
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            <a href="{{ route('purchase_orders.show', $order) }}"><i class="ri-eye-line me-2 text-muted"></i></a>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Acciones">
                                    <i class="ri-more-2-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">

                                    @if ($mode === 'trashed')
                                        {{-- Papelera: solo restaurar y eliminar permanentemente --}}
                                        <li>
                                            <form action="{{ route('purchase_orders.restore', $order->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="ri-arrow-go-back-line me-2 text-muted"></i>Restaurar
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('purchase_orders.force_destroy', $order->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Seguro que deseas ELIMINAR PERMANENTEMENTE la OC #{{ $order->folio }}? Esta acción no se puede deshacer.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger fw-semibold">
                                                    <i class="ri-delete-bin-2-line me-2"></i>Eliminar permanentemente
                                                </button>
                                            </form>
                                        </li>

                                    @else
                                        {{-- Index y Archivadas: ver, editar (solo index), PDF --}}
                                        {{--  
                                        <li>
                                            <a class="dropdown-item" href="{{ route('purchase_orders.show', $order) }}">
                                                <i class="ri-eye-line me-2 text-muted"></i>Ver detalle
                                            </a>
                                        </li>
                                        --}}
                                        @if ($mode === 'index')
                                            @hasanyrole('admin|Orden de compra')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('purchase_orders.edit', $order) }}">
                                                    <i class="ri-pencil-line me-2 text-muted"></i>Editar
                                                </a>
                                            </li>
                                            @endhasanyrole
                                        @endif
                                        <li>
                                            <a class="dropdown-item" href="{{ route('purchase_orders.pdf', $order) }}" target="_blank">
                                                <i class="ri-file-pdf-2-line me-2 text-muted"></i>Descargar PDF
                                            </a>
                                        </li>
                                        @hasanyrole('admin|Orden de compra')
                                        <li><hr class="dropdown-divider"></li>
                                        @if ($mode === 'index')
                                            <li>
                                                <form action="{{ route('purchase_orders.archive', $order) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ri-archive-line me-2 text-muted"></i>Archivar
                                                    </button>
                                                </form>
                                            </li>
                                        @else
                                            <li>
                                                <form action="{{ route('purchase_orders.unarchive', $order) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ri-inbox-unarchive-line me-2 text-muted"></i>Restaurar al listado
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                        @can('delete')
                                        <li>
                                            <form action="{{ route('purchase_orders.destroy', $order) }}"
                                                method="POST"
                                                onsubmit="return submitPurchaseOrderDelete(this, '{{ $order->folio ?? $order->id }}');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="deletion_comment" value="">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="ri-delete-bin-line me-2"></i>Eliminar
                                                </button>
                                            </form>
                                        </li>
                                        @endcan
                                        @endhasanyrole
                                    @endif

                                </ul>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="po-trace-map" aria-label="Mapa de trazabilidad de la orden">
                            @if ($solmat)
                                <a href="{{ route('material_requests.show', $solmat) }}"
                                   class="po-trace-node po-trace-node-solmat"
                                   data-bs-toggle="tooltip"
                                   data-bs-placement="top"
                                   title="SOLMAT #{{ $solmat->folio ?? $solmat->id }}"
                                   aria-label="Ver SOLMAT #{{ $solmat->folio ?? $solmat->id }}">
                                    <i class="ri-hammer-line"></i>
                                </a>
                                <span class="po-trace-link" aria-hidden="true"></span>
                            @endif

                            @if ($solcom)
                                <a href="{{ route('purchase_requests.show', $solcom) }}"
                                   class="po-trace-node po-trace-node-solcom"
                                   data-bs-toggle="tooltip"
                                   data-bs-placement="top"
                                   title="SOLCOM #{{ $solcom->folio ?? $solcom->id }}"
                                   aria-label="Ver SOLCOM #{{ $solcom->folio ?? $solcom->id }}">
                                    <i class="ri-file-text-line"></i>
                                </a>
                                <span class="po-trace-link" aria-hidden="true"></span>
                            @endif

                            <a href="{{ route('purchase_orders.show', $order) }}"
                               class="po-trace-node po-trace-node-oc"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               title="OC #{{ $order->folio ?? $order->id }}"
                               aria-label="Ver OC #{{ $order->folio ?? $order->id }}">
                                <i class="ri-shopping-bag-3-line"></i>
                            </a>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12 font-monospace">#{{ $order->folio ?? '—' }}</span>
                    </td>
                    <td class="po-supplier-cell">
                        @if ($mode !== 'trashed')
                            <a href="{{ route('suppliers.show', $order->supplier) }}" class="text-dark fw-medium" title="{{ $supplierName }}">
                                {{ $supplierNameShort }}
                            </a>
                        @else
                            <span title="{{ $supplierName }}">{{ $supplierNameShort }}</span>
                        @endif
                    </td>
                    <td style="max-width:160px">
                        @php
                            $proj = $order->projectRelation?->name ?? $order->project ?? null;
                            $obra = $order->workRelation?->name  ?? $order->site    ?? null;
                        @endphp
                        @if ($proj)
                            <div class="hover-marquee" style="--marquee-width:150px;" title="{{ $proj }}">
                                <span class="track"><span>{{ $proj }}</span><span aria-hidden="true">{{ $proj }}</span></span>
                            </div>
                        @endif
                        @if ($obra)
                            <small class="text-muted d-block hover-marquee" style="--marquee-width:150px;" title="{{ $obra }}">
                                <span class="track"><span>{{ $obra }}</span><span aria-hidden="true">{{ $obra }}</span></span>
                            </small>
                        @endif
                        @if (!$proj && !$obra)—@endif
                    </td>
                    <td>{{ $order->next_due_date ?? '—' }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($order->total_with_iva, 2) }} {{ $currencyCode ?: '—' }}</td>
                    <td><i class="ri-money-dollar-circle-line me-1 text-muted"></i>{{ number_format($order->saldo_cubierto, 2) }}</td>
                    <td>
                        <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                    </td>

                    {{-- Columna condicional: fecha de archivo o eliminación --}}
                    @if ($mode === 'archived')
                        <td>
                            <small class="text-muted">{{ $order->archived_at->format('d/m/Y H:i') }}</small>
                        </td>
                    @elseif ($mode === 'trashed')
                        <td>
                            <small class="text-danger">{{ $order->deleted_at->format('d/m/Y H:i') }}</small>
                        </td>
                    @endif

                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="text-center text-muted py-4">
                        @if ($mode === 'archived')
                            No hay órdenes de compra archivadas.
                        @elseif ($mode === 'trashed')
                            La papelera está vacía.
                        @else
                            No hay órdenes de compra registradas.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@once
    @push('styles')
    <style>
    .po-orders-table-responsive {
        overflow: visible;
    }

    .po-orders-table-responsive .dropdown-menu {
        z-index: 1085;
    }

    .po-supplier-cell {
        max-width: 360px;
    }

    .po-trace-map {
        display: inline-flex;
        align-items: center;
        gap: 0;
        min-width: 88px;
    }

    .po-trace-node {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        border: 2px solid transparent;
        font-size: 12px;
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .po-trace-node:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
    }

    .po-trace-node-solmat {
        background: #f3ecff;
        border-color: #6f42c1;
        color: #6f42c1;
    }

    .po-trace-node-solcom {
        background: #fff3cd;
        border-color: #ffc107;
        color: #b78600;
    }

    .po-trace-node-oc {
        background: #e7f1ff;
        border-color: #0d6efd;
        color: #0d6efd;
    }

    .po-trace-link {
        width: 14px;
        border-top: 2px solid #adb5bd;
        margin: 0 2px;
    }

    @media (max-width: 1199.98px) {
        .po-orders-table-responsive {
            overflow-x: auto;
            overflow-y: visible;
        }

        .po-supplier-cell {
            max-width: 260px;
        }

        .po-trace-map {
            min-width: 82px;
        }

        .po-trace-link {
            width: 10px;
            margin: 0 1px;
        }
    }
    </style>
    @endpush

    @push('scripts')
    <script>
    function submitPurchaseOrderDelete(form, folio) {
        var comment = window.prompt('Escribe el motivo de eliminación para la OC #' + folio + ':');
        if (comment === null) return false;

        comment = String(comment).trim();
        if (!comment) {
            alert('El comentario de eliminación es obligatorio.');
            return false;
        }

        var input = form.querySelector('input[name="deletion_comment"]');
        if (!input) return false;
        input.value = comment;

        return confirm('¿Seguro que deseas eliminar esta orden de compra?');
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!window.bootstrap || !window.bootstrap.Tooltip) return;

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (!bootstrap.Tooltip.getInstance(el)) {
                new bootstrap.Tooltip(el);
            }
        });
    });
    </script>
    @endpush
@endonce
