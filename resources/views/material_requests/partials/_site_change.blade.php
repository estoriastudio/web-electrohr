{{--
    Fase 4: estado de un cambio de SOLMAT pedido desde la obra y, para el Gerente del proyecto,
    sus botones de autorizar o rechazar. Recibe $note y $materialRequest.
--}}
@if ($note->isSiteChange())
    <div class="mt-1">
        <span class="badge bg-info-subtle text-info"><i class="ri-building-line me-1"></i>Cambio pedido desde la obra</span>
        @if ($note->authorization_status === 'pending')
            <span class="badge bg-warning-subtle text-warning"><i class="ri-shield-user-line me-1"></i>Espera al Gerente del proyecto</span>
            @if (auth()->user()->hasRole('admin') || (auth()->user()->can('material_request_changes.authorize') && $materialRequest->project?->isManagedBy(auth()->user())))
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <form action="{{ route('material_requests.site_changes.decide', [$materialRequest, $note]) }}" method="POST">
                        @csrf
                        <input type="hidden" name="decision" value="approved">
                        <button type="submit" class="btn btn-success btn-sm"><i class="ri-check-line me-1"></i>Autorizar cambio</button>
                    </form>
                    <form action="{{ route('material_requests.site_changes.decide', [$materialRequest, $note]) }}" method="POST" class="d-flex gap-2">
                        @csrf
                        <input type="hidden" name="decision" value="rejected">
                        <input type="text" name="authorization_note" class="form-control form-control-sm" placeholder="Motivo del rechazo" required maxlength="2000">
                        <button type="submit" class="btn btn-outline-danger btn-sm text-nowrap">Rechazar</button>
                    </form>
                </div>
            @endif
        @elseif ($note->authorization_status === 'approved')
            <span class="badge bg-success-subtle text-success"><i class="ri-shield-check-line me-1"></i>Autorizado por {{ $note->authorizedBy?->name }}{{ $note->isResolved() ? '' : ' · Almacén lo aplica' }}</span>
        @elseif ($note->authorization_status === 'rejected')
            <span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>Rechazado por {{ $note->authorizedBy?->name }}</span>
            @if ($note->authorization_note)
                <div class="fs-12 text-muted">{{ $note->authorization_note }}</div>
            @endif
        @endif
    </div>
@endif
