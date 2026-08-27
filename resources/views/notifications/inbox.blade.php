@extends('layouts.app')

@section('page_title', 'Notificaciones')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Notificaciones</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-0"><i class="ri-notification-3-line me-2 text-primary"></i>Notificaciones</h4>
                    <p class="text-muted fs-12 mb-0 mt-1">Avisos recibidos sobre solicitudes de material.</p>
                </div>
                <button id="btn-mark-inbox-read" type="button" class="btn btn-light btn-sm" data-url="{{ route('notifications.markAllRead') }}">
                    <i class="ri-check-double-line me-1"></i>Marcar todas como leídas
                </button>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse ($notifications as $notification)
                        <a href="{{ $notification->type === 'material_request_commitment' ? route('material_requests.show', $notification->model_id) : '#' }}"
                           class="list-group-item list-group-item-action py-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="avatar-sm shrink-0">
                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle"><i class="ri-archive-stack-line"></i></span>
                                </span>
                                <div class="grow">
                                    <p class="mb-1"><span class="fw-semibold">{{ $notification->user?->name ?? 'Almacén' }}</span> {{ $notification->data }}</p>
                                    <small class="text-muted">{{ $notification->created_at->format('d/m/Y H:i') }} · {{ $notification->created_at->diffForHumans() }}</small>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="text-center text-muted py-5">
                            <i class="ri-notification-off-line fs-24 d-block mb-2 opacity-50"></i>
                            No tienes notificaciones de SOLMAT pendientes.
                        </div>
                    @endforelse
                </div>
            </div>
            @if ($notifications->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $notifications->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('btn-mark-inbox-read');
    if (!button) return;

    button.addEventListener('click', function () {
        fetch(button.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        }).then(function (response) {
            if (response.ok) window.location.reload();
        });
    });
});
</script>
@endpush