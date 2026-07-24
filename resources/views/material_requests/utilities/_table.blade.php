{{--
    Partial unificado para las tres vistas de SOLMATs.
    El modo se detecta automáticamente desde la ruta activa:
      - 'index'    → material_requests.index
      - 'archived' → material_requests.archived
      - 'trashed'  → material_requests.soft_deleted
--}}
@php
    $mode = request()->routeIs('material_requests.archived')
        ? 'archived'
        : (request()->routeIs('material_requests.soft_deleted') ? 'trashed' : 'index');

    $colspan = 11 + ($mode !== 'index' ? 1 : 0);

    $statusMap = [
        'pending'           => ['label' => 'Pendiente',         'class' => 'bg-warning-subtle text-warning'],
        'sent_to_warehouse' => ['label' => 'Enviado a Almacén', 'class' => 'bg-secondary-subtle text-secondary'],
        'changes_requested' => ['label' => 'Cambios Solicitados','class' => 'bg-danger-subtle text-danger'],
        'linked'            => ['label' => 'Ligado',            'class' => 'bg-info-subtle text-info'],
        'completed'         => ['label' => 'Finalizado',        'class' => 'bg-success-subtle text-success'],
    ];
@endphp

<div class="table-responsive solmat-table-responsive">
    <table class="table align-middle table-hover table-centered mb-0 app-list-table">
        <thead class="bg-light-subtle">
            <tr>
                <th>Acciones</th>
                <th>Trazabilidad</th>
                <th>Folio</th>
                <th>Código</th>
                <th>Proyecto / Obras</th>
                <th>Ubicación</th>
                <th>F. Solicitud</th>
                <th>Categoría</th>
                <th>Elaborada por</th>
                <th>Compromiso</th>
                <th>Estado</th>
                @if ($mode === 'archived')
                    <th>Archivada el</th>
                @elseif ($mode === 'trashed')
                    <th>Eliminada el</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($materialRequests as $mr)
                @php
                    $s    = $statusMap[$mr->status] ?? ['label' => $mr->status, 'class' => 'bg-secondary-subtle text-secondary'];
                    $proj = $mr->project?->name ?? null;
                    $obra = $mr->projectWorks->pluck('name')->implode(' · ');
                    $obra = $obra !== '' ? $obra : null;
                    $firstSolcom = $mr->purchaseRequests->first() ?? null;
                    $firstOc     = $firstSolcom?->purchaseOrders->first() ?? null;
                    $committedPercent = $mr->committed_percent;
                    $commitmentClass = $committedPercent >= 100
                        ? 'bg-danger-subtle text-danger'
                        : ($committedPercent > 0 ? 'bg-warning-subtle text-warning' : 'bg-light text-muted border');
                @endphp
                <tr @if ($mode === 'trashed') class="table-danger" @endif>

                    {{-- Menú de acciones --}}
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            @if ($mode !== 'trashed')
                                <a href="{{ route('material_requests.show', $mr) }}" title="Ver detalle">
                                    <i class="ri-eye-line me-2 text-muted"></i>
                                </a>
                            @endif
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false" title="Acciones">
                                    <i class="ri-more-2-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">

                                    @if ($mode === 'trashed')
                                        {{-- Papelera: solo restaurar y eliminar permanentemente --}}
                                        <li>
                                            <form action="{{ route('material_requests.restore', $mr->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="ri-arrow-go-back-line me-2 text-muted"></i>Restaurar
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('material_requests.force_destroy', $mr->id) }}"
                                                  method="POST"
                                                  class="js-solmat-force-delete-form"
                                                  data-solmat-folio="{{ $mr->folio }}"
                                                  data-solmat-id="{{ $mr->id }}"
                                                  data-solcom-count="{{ $mr->purchaseRequests->count() }}"
                                                  data-oc-count="{{ $mr->purchaseRequests->flatMap->purchaseOrders->count() }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger fw-semibold">
                                                    <i class="ri-delete-bin-2-line me-2"></i>Eliminar permanentemente
                                                </button>
                                            </form>
                                        </li>

                                    @else
                                        {{-- Index y Archivadas --}}
                                        <li>
                                            <a class="dropdown-item" href="{{ route('material_requests.show', $mr) }}">
                                                <i class="ri-eye-line me-2 text-muted"></i>Ver detalle
                                            </a>
                                        </li>
                                        @if ($mode === 'index')
                                            @hasanyrole('admin|Solmat')
                                            @can('update')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('material_requests.edit', $mr) }}">
                                                    <i class="ri-pencil-line me-2 text-muted"></i>Editar
                                                </a>
                                            </li>
                                            @endcan
                                            @endhasanyrole
                                        @endif
                                        <li>
                                            <a class="dropdown-item" href="{{ route('material_requests.pdf', $mr) }}" target="_blank">
                                                <i class="ri-file-pdf-2-line me-2 text-muted"></i>Descargar PDF
                                            </a>
                                        </li>
                                        @hasanyrole('admin|Solmat')
                                        <li><hr class="dropdown-divider"></li>
                                        @if ($mode === 'index')
                                            <li>
                                                <form action="{{ route('material_requests.archive', $mr) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ri-archive-line me-2 text-muted"></i>Archivar
                                                    </button>
                                                </form>
                                            </li>
                                        @else
                                            <li>
                                                <form action="{{ route('material_requests.unarchive', $mr) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ri-inbox-unarchive-line me-2 text-muted"></i>Restaurar al listado
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                        @can('delete')
                                        @if ($mode === 'index')
                                        <li>
                                            <form action="{{ route('material_requests.destroy', $mr) }}"
                                                  method="POST"
                                                  onsubmit="return submitSolmatDelete(this, '{{ $mr->folio }}');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="deletion_comment" value="">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="ri-delete-bin-line me-2"></i>Eliminar
                                                </button>
                                            </form>
                                        </li>
                                        @endif
                                        @endcan
                                        @endhasanyrole
                                    @endif

                                </ul>
                            </div>
                        </div>
                    </td>

                    {{-- Trazabilidad --}}
                    <td>
                        <div class="po-trace-map" aria-label="Mapa de trazabilidad">
                            <a href="{{ route('material_requests.show', $mr) }}"
                               class="po-trace-node po-trace-node-solmat"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               title="SOLMAT #{{ $mr->folio ?? $mr->id }}"
                               aria-label="Ver SOLMAT #{{ $mr->folio ?? $mr->id }}">
                                <i class="ri-hammer-line"></i>
                            </a>

                            @if ($firstSolcom)
                                <span class="po-trace-link" aria-hidden="true"></span>
                                <a href="{{ route('purchase_requests.show', $firstSolcom) }}"
                                   class="po-trace-node po-trace-node-solcom"
                                   data-bs-toggle="tooltip"
                                   data-bs-placement="top"
                                   title="SOLCOM #{{ $firstSolcom->folio ?? $firstSolcom->id }}"
                                   aria-label="Ver SOLCOM #{{ $firstSolcom->folio ?? $firstSolcom->id }}">
                                    <i class="ri-file-text-line"></i>
                                </a>
                            @endif

                            @if ($firstOc)
                                <span class="po-trace-link" aria-hidden="true"></span>
                                <a href="{{ route('purchase_orders.show', $firstOc) }}"
                                   class="po-trace-node po-trace-node-oc"
                                   data-bs-toggle="tooltip"
                                   data-bs-placement="top"
                                   title="OC #{{ $firstOc->folio ?? $firstOc->id }}"
                                   aria-label="Ver OC #{{ $firstOc->folio ?? $firstOc->id }}">
                                    <i class="ri-shopping-bag-3-line"></i>
                                </a>
                            @endif
                        </div>
                    </td>

                    <td>
                        <div class="fw-semibold">#{{ $mr->folio }}</div>
                        <small class="text-muted">{{ $mr->need_date?->format('d/m/Y') ?: '—' }}</small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $mr->code ?? '—' }}</span>
                    </td>
                    <td style="max-width: 220px;">
                        @if ($proj)
                            <div class="hover-marquee" title="{{ $proj }}">
                                <span class="track"><span>{{ $proj }}</span><span aria-hidden="true">{{ $proj }}</span></span>
                            </div>
                        @endif
                        @if ($obra)
                            <small class="text-muted d-block hover-marquee mt-1" title="{{ $obra }}">
                                <span class="track"><span>{{ $obra }}</span><span aria-hidden="true">{{ $obra }}</span></span>
                            </small>
                        @endif
                        @if (!$proj && !$obra)
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-wrap" style="max-width: 200px;">{{ $mr->zone }}</td>
                    <td>{{ $mr->request_date?->format('d/m/Y') }}</td>
                    <td>{{ $mr->supply_category }}</td>
                    <td>{{ $mr->requestedBy?->name ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $commitmentClass }} py-1 px-2 fs-12">
                            {{ number_format($committedPercent, 0) }}%
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                    </td>

                    {{-- Columna condicional: fecha de archivo o eliminación --}}
                    @if ($mode === 'archived')
                        <td>
                            <small class="text-muted">{{ $mr->archived_at->format('d/m/Y H:i') }}</small>
                        </td>
                    @elseif ($mode === 'trashed')
                        <td>
                            <small class="text-danger">{{ $mr->deleted_at->format('d/m/Y H:i') }}</small>
                        </td>
                    @endif

                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="text-center text-muted py-4">
                        @if ($mode === 'archived')
                            No hay SOLMATs archivadas.
                        @elseif ($mode === 'trashed')
                            La papelera está vacía.
                        @else
                            No hay solicitudes de material registradas.
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
    .solmat-table-responsive {
        overflow: visible;
    }

    .solmat-table-responsive .dropdown-menu {
        z-index: 1085;
    }

    .po-trace-map {
        display: inline-flex;
        align-items: center;
        gap: 0;
        min-width: 64px;
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
        .solmat-table-responsive {
            overflow-x: auto;
            overflow-y: visible;
        }

        .po-trace-map {
            min-width: 56px;
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
    function submitSolmatDelete(form, folio) {
        var comment = window.prompt('Escribe el motivo de eliminación para la SOLMAT #' + folio + ':');
        if (comment === null) return false;

        comment = String(comment).trim();
        if (!comment) {
            alert('El comentario de eliminación es obligatorio.');
            return false;
        }

        var input = form.querySelector('input[name="deletion_comment"]');
        if (!input) return false;
        input.value = comment;

        return confirm('¿Seguro que deseas eliminar esta SOLMAT?');
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
