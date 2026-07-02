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
    $colspan = 11 + ($mode !== 'index' ? 1 : 0);
@endphp

<div class="table-responsive">
    <table class="table align-middle table-hover table-centered mb-0 app-list-table">
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
                @if ($mode === 'archived')
                    <th>Archivada el</th>
                @elseif ($mode === 'trashed')
                    <th>Eliminada el</th>
                @endif
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
                <tr @if ($mode === 'trashed') class="table-danger" @endif>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12 font-monospace">#{{ $order->folio ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="badge {{ $t['class'] }} py-1 px-2 fs-12">{{ $t['label'] }}</span>
                    </td>
                    <td>
                        @if ($mode !== 'trashed')
                            <a href="{{ route('suppliers.show', $order->supplier) }}" class="text-dark fw-medium">
                                {{ $order->supplier->rfc_name ?? $order->supplier->commercial_name ?? '—' }}
                            </a>
                        @else
                            {{ $order->supplier->rfc_name ?? $order->supplier->commercial_name ?? '—' }}
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
                    <td>
                        @if ($order->parent_id)
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
                                              onsubmit="return confirm('¿Seguro que deseas eliminar esta orden de compra? Pasará a la papelera y podrá ser eliminada permanentemente por un administrador.');">
                                            @csrf
                                            @method('DELETE')
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
