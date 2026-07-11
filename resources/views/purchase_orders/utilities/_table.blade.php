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
                <th>Folio</th>
                <th>Proveedor</th>
                <th>Proyecto / Obra</th>
                <th>Próx. Vencimiento</th>
                <th>Moneda</th>
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

                @endphp
                <tr @if ($mode === 'trashed') class="table-danger" @endif>
                    {{-- Menú de acciones --}}
                    <td>
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
                                    <li>
                                        <a class="dropdown-item" href="{{ route('purchase_orders.show', $order) }}">
                                            <i class="ri-eye-line me-2 text-muted"></i>Ver detalle
                                        </a>
                                    </li>
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
                    <td>
                        <span class="badge bg-light text-dark border py-1 px-2 fs-12">{{ $order->currency }}</span>
                    </td>
                    <td><i class="ri-money-dollar-circle-line me-1 text-muted"></i>{{ number_format($order->total_with_iva, 2) }}</td>
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

    @media (max-width: 1199.98px) {
        .po-orders-table-responsive {
            overflow-x: auto;
            overflow-y: visible;
        }

        .po-supplier-cell {
            max-width: 260px;
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
    </script>
    @endpush
@endonce
