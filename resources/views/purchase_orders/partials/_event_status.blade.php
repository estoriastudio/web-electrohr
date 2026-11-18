{{-- ¿El hito está abierto o cerrado? Requiere $eventStatus (MilestoneEventStatus). --}}
@php
    $dimension = fn (string $label, string $state) => [
        'label' => $label,
        'class' => ['complete' => 'bg-success-subtle text-success', 'partial' => 'bg-info-subtle text-info', 'pending' => 'bg-danger-subtle text-danger'][$state],
        'icon' => ['complete' => 'ri-checkbox-circle-line', 'partial' => 'ri-progress-3-line', 'pending' => 'ri-close-circle-line'][$state],
        'done' => $state === 'complete',
    ];
    $dimensions = [
        $dimension('Cumplimiento', $eventStatus['fulfillment'] ? 'complete' : 'pending'),
        $dimension('Facturación', $eventStatus['invoicing'] ? 'complete' : 'pending'),
        $dimension($eventStatus['payment'] === 'partial' ? 'Pago parcial' : 'Pago', $eventStatus['payment']),
    ];
    $doneCount = collect($dimensions)->where('done', true)->count();
@endphp
<div>
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
            <span class="fs-12 fw-semibold {{ $eventStatus['closed'] ? 'text-success' : '' }}">
                <i class="{{ $eventStatus['closed'] ? 'ri-checkbox-circle-line' : 'ri-flag-line' }} me-1"></i>{{ $eventStatus['closed'] ? 'Hito cerrado' : 'Hito abierto' }}
            </span>
            <small class="d-block fs-11 text-muted">
                {{ $eventStatus['closed'] ? 'Cumplimiento, facturación y pago completos' : 'Faltan eventos para cerrarlo' }}
            </small>
        </div>
        @unless ($eventStatus['closed'])
            <span class="badge {{ $eventStatus['class'] }} fs-11">{{ $doneCount }} de 3 eventos</span>
        @endunless
    </div>
    @unless ($eventStatus['closed'])
        <div class="d-flex flex-wrap gap-1 mt-1">
            @foreach ($dimensions as $item)
                <span class="badge {{ $item['class'] }} fs-11"><i class="{{ $item['icon'] }} me-1"></i>{{ $item['label'] }}</span>
            @endforeach
        </div>
    @endunless
    @if ($eventStatus['regularization_pending'])
        <div class="fs-11 text-warning mt-1"><i class="ri-file-warning-line me-1"></i>Pago realizado pendiente de regularización documental (falta factura aceptada).</div>
    @endif
</div>
