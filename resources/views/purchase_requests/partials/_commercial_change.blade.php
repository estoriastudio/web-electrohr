{{--
    Fase 4: estado de un cambio de SOLCOM marcado como ajuste comercial y, para Dirección,
    sus botones de autorizar o rechazar. Recibe $note y $purchaseRequest.
--}}
@if ($note->is_commercial)
    <div class="mt-1">
        @if ($note->direction_status === 'pending')
            <span class="badge bg-warning-subtle text-warning"><i class="ri-shield-user-line me-1"></i>Ajuste comercial: espera a Dirección</span>
            @can('purchase_request_changes.authorize')
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <form action="{{ route('purchase_requests.change_notes.decide', [$purchaseRequest, $note]) }}" method="POST">
                        @csrf
                        <input type="hidden" name="decision" value="approved">
                        <button type="submit" class="btn btn-success btn-sm"><i class="ri-check-line me-1"></i>Autorizar ajuste</button>
                    </form>
                    <form action="{{ route('purchase_requests.change_notes.decide', [$purchaseRequest, $note]) }}" method="POST" class="d-flex gap-2">
                        @csrf
                        <input type="hidden" name="decision" value="rejected">
                        <input type="text" name="direction_note" class="form-control form-control-sm" placeholder="Motivo del rechazo" required maxlength="2000">
                        <button type="submit" class="btn btn-outline-danger btn-sm text-nowrap">Rechazar</button>
                    </form>
                </div>
            @endcan
        @elseif ($note->direction_status === 'approved')
            <span class="badge bg-success-subtle text-success"><i class="ri-shield-check-line me-1"></i>Ajuste comercial autorizado por {{ $note->directionDecidedBy?->name }} · {{ $note->direction_decided_at?->format('d/m/Y') }}</span>
        @elseif ($note->direction_status === 'rejected')
            <span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>Ajuste comercial rechazado por {{ $note->directionDecidedBy?->name }}</span>
            @if ($note->direction_note)
                <div class="fs-12 text-muted">{{ $note->direction_note }}</div>
            @endif
        @endif
    </div>
@endif
