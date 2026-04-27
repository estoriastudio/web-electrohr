@php
    $today = \Carbon\Carbon::today();
    $urgentDate = \Carbon\Carbon::now()->addDays(7);

    $cardsData = $payments->map(function ($payment) use ($today, $urgentDate) {
        $milestone     = $payment->milestone;
        $purchaseOrder = $milestone?->purchaseOrder;
        $supplier      = $purchaseOrder?->supplier;
        $dueDate       = $milestone?->due_date;

        // Semáforo
        if (!$dueDate) {
            $urgency = 'ok';
        } elseif ($dueDate->lt($today)) {
            $urgency = 'vencido';
        } elseif ($dueDate->lte($urgentDate)) {
            $urgency = 'proximo';
        } else {
            $urgency = 'ok';
        }

        return [
            'id'             => $payment->id,
            'folio'          => $payment->folio,
            'amount'         => number_format($payment->amount, 2),
            'amount_raw'     => $payment->amount,
            'currency'       => $purchaseOrder?->currency ?? 'MXN',
            'supplier'       => $supplier?->rfc_name ?? '—',
            'po_id'          => $purchaseOrder?->id ?? '—',
            'po_project'     => $purchaseOrder?->project ?? null,
            'milestone_type' => $milestone?->type === 'anticipo' ? 'Anticipo' : 'Pago Regular',
            'due_date'       => $dueDate?->format('d/m/Y') ?? null,
            'urgency'        => $urgency,
            'swipe_url'      => route('payments.swipe', $payment->id),
        ];
    })->values()->toArray();
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('electrohr_swipable_cards/css/swipable.cards.css') }}">
@endpush   

@if($payments->isEmpty())
    <div class="swipe-empty">
        <div class="empty-icon">🎉</div>
        <h4>¡Todo al día!</h4>
        <p>No hay pagos pendientes de autorización en este momento.</p>
        <a href="{{ route('payments.index') }}" class="btn-back-list mt-3">
            <i class="ri-arrow-left-line"></i> Ver listado de pagos
        </a>
    </div>
@else
<div class="swipe-widget" id="swipeWidget">

    <div class="swipe-progress">
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <span class="progress-text" id="progressText"></span>
    </div>

    <div class="card-stack" id="cardStack"></div>

    <div class="swipe-buttons" id="swipeButtons">
        <button class="swipe-btn btn-rechazar" id="btnRechazar" aria-label="Rechazar">
            <i class="ri-close-line"></i>
        </button>
        <button class="swipe-btn btn-autorizar" id="btnAutorizar" aria-label="Autorizar">
            <i class="ri-check-line"></i>
        </button>
    </div>

    <div class="undo-container">
        <button class="btn-undo" id="btnUndo">
            <i class="ri-arrow-go-back-line"></i> Deshacer
        </button>
    </div>
    <div class="undo-timer-bar" id="undoTimerBar">
        <div class="undo-timer-fill" id="undoTimerFill"></div>
    </div>

    <div class="swipe-complete" id="swipeComplete">
        <div class="complete-icon">✅</div>
        <h3 class="complete-title">Revisión completada</h3>
        <div class="complete-stats">
            <div class="stat-pill pill-autorizado">
                <span class="stat-number" id="countAutorizado">0</span>
                <span class="stat-label">Autorizados</span>
            </div>
            <div class="stat-pill pill-rechazado">
                <span class="stat-number" id="countRechazado">0</span>
                <span class="stat-label">Rechazados</span>
            </div>
        </div>
        <a href="{{ route('payments.index') }}" class="btn-back-list">
            <i class="ri-file-list-3-line"></i> Ver listado de pagos
        </a>
    </div>

</div>
@endif

@push('scripts')
    <script>
        window.paymentsData = @json($cardsData);
    </script>
    <script src="{{ asset('electrohr_swipable_cards/js/swipable.cards.js') }}"></script>
@endpush