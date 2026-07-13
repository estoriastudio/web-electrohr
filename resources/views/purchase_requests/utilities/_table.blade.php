{{--
    Partial unificado para SOLCOM.
    Modos:
      - index    -> purchase_requests.index
      - archived -> purchase_requests.archived
      - trashed  -> purchase_requests.soft_deleted
--}}
@php
    $mode = request()->routeIs('purchase_requests.archived')
        ? 'archived'
        : (request()->routeIs('purchase_requests.soft_deleted') ? 'trashed' : 'index');

    $colspan = 7 + ($mode !== 'index' ? 1 : 0);
@endphp

<div class="table-responsive pr-requests-table-responsive">
    <table class="table align-middle table-hover table-centered mb-0 app-list-table">
        <thead class="bg-light-subtle">
            <tr>
                <th>Acciones</th>
                <th>Folio</th>
                <th>Código</th>
                <th>SOLMAT</th>
                <th>Proyecto / Obra</th>
                <th>F. Solicitud</th>
                <th>Estado</th>
                @if ($mode === 'archived')
                    <th>Archivada el</th>
                @elseif ($mode === 'trashed')
                    <th>Eliminada el</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
                $statusMap = [
                    'pending'            => ['label' => 'Pendiente',           'class' => 'bg-warning-subtle text-warning'],
                    'linked'             => ['label' => 'Ligado',              'class' => 'bg-info-subtle text-info'],
                    'sent_to_purchasing' => ['label' => 'En Compras',          'class' => 'bg-primary-subtle text-primary'],
                    'changes_requested'  => ['label' => 'Cambios Solicitados', 'class' => 'bg-danger-subtle text-danger'],
                    'completed'          => ['label' => 'Finalizado',          'class' => 'bg-success-subtle text-success'],
                ];
            @endphp

            @forelse ($purchaseRequests as $pr)
                @php
                    $s = $statusMap[$pr->status] ?? ['label' => $pr->status, 'class' => 'bg-secondary-subtle text-secondary'];
                @endphp
                <tr @if ($mode === 'trashed') class="table-danger" @endif>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('purchase_requests.show', $pr) }}"><i class="ri-eye-line me-2 text-muted"></i></a>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Acciones">
                                    <i class="ri-more-2-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @if ($mode === 'trashed')
                                        <li>
                                            <form action="{{ route('purchase_requests.restore', $pr->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="ri-arrow-go-back-line me-2 text-muted"></i>Restaurar
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('purchase_requests.force_destroy', $pr->id) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Seguro que deseas ELIMINAR PERMANENTEMENTE la SOLCOM #{{ $pr->folio }}? Esta acción no se puede deshacer.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger fw-semibold">
                                                    <i class="ri-delete-bin-2-line me-2"></i>Eliminar permanentemente
                                                </button>
                                            </form>
                                        </li>
                                    @else
                                        {{--  
                                        <li>
                                            <a class="dropdown-item" href="{{ route('purchase_requests.show', $pr) }}">
                                                <i class="ri-eye-line me-2 text-muted"></i>Ver detalle
                                            </a>
                                        </li>
                                        --}}

                                        @if ($mode === 'index')
                                            @hasanyrole('admin|Solcom')
                                            @can('update')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('purchase_requests.edit', $pr) }}">
                                                    <i class="ri-pencil-line me-2 text-muted"></i>Editar
                                                </a>
                                            </li>
                                            @endcan
                                            @endhasanyrole
                                        @endif

                                        <li>
                                            <a class="dropdown-item" href="{{ route('purchase_requests.pdf', $pr) }}" target="_blank">
                                                <i class="ri-file-pdf-2-line me-2 text-muted"></i>Descargar PDF
                                            </a>
                                        </li>

                                        @hasanyrole('admin|Solcom|Orden de compra')
                                        <li><hr class="dropdown-divider"></li>
                                        @if ($mode === 'index')
                                            <li>
                                                <form action="{{ route('purchase_requests.archive', $pr) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ri-archive-line me-2 text-muted"></i>Archivar
                                                    </button>
                                                </form>
                                            </li>
                                        @else
                                            <li>
                                                <form action="{{ route('purchase_requests.unarchive', $pr) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ri-inbox-unarchive-line me-2 text-muted"></i>Restaurar al listado
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                        @endhasanyrole

                                        @hasanyrole('admin|Solcom')
                                        @can('delete')
                                        <li>
                                            <form action="{{ route('purchase_requests.destroy', $pr) }}"
                                                method="POST"
                                                onsubmit="return submitPurchaseRequestDelete(this, '{{ $pr->folio ?? $pr->id }}');">
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
                    <td><span class="fw-semibold">{{ $pr->folio }}</span></td>
                    <td>{{ $pr->code ?? '—' }}</td>
                    <td>
                        @if ($pr->materialRequest)
                            <a href="{{ route('material_requests.show', $pr->materialRequest) }}"
                               class="text-primary fw-semibold text-decoration-none">
                                #{{ $pr->materialRequest->folio }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="max-width: 220px;">
                        @php
                            $proj = $pr->project?->name;
                            $obra = $pr->projectWork?->name;
                        @endphp
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
                    <td>{{ $pr->request_date?->format('d/m/Y') }}</td>
                    <td><span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span></td>

                    @if ($mode === 'archived')
                        <td><small class="text-muted">{{ $pr->archived_at?->format('d/m/Y H:i') }}</small></td>
                    @elseif ($mode === 'trashed')
                        <td><small class="text-danger">{{ $pr->deleted_at?->format('d/m/Y H:i') }}</small></td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="text-center text-muted py-4">
                        @if ($mode === 'archived')
                            No hay SOLCOM archivadas.
                        @elseif ($mode === 'trashed')
                            La papelera de SOLCOM está vacía.
                        @else
                            No hay solicitudes de compra registradas.
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
    .pr-requests-table-responsive {
        overflow: visible;
    }

    .pr-requests-table-responsive .dropdown-menu {
        z-index: 1085;
    }

    @media (max-width: 1199.98px) {
        .pr-requests-table-responsive {
            overflow-x: auto;
            overflow-y: visible;
        }
    }
    </style>
    @endpush

    @push('scripts')
    <script>
    function submitPurchaseRequestDelete(form, folio) {
        var comment = window.prompt('Escribe el motivo de eliminación para la SOLCOM #' + folio + ':');
        if (comment === null) return false;

        comment = String(comment).trim();
        if (!comment) {
            alert('El comentario de eliminación es obligatorio.');
            return false;
        }

        var input = form.querySelector('input[name="deletion_comment"]');
        if (!input) return false;
        input.value = comment;

        return confirm('¿Seguro que deseas eliminar esta SOLCOM?');
    }
    </script>
    @endpush
@endonce
