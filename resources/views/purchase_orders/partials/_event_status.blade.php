{{-- Estado del hito como evento de pago. Requiere $eventStatus (MilestoneEventStatus). --}}
@php
    $dimension = fn (string $label, string $state) => [
        'label' => $label,
        'class' => ['complete' => 'bg-success-subtle text-success', 'partial' => 'bg-info-subtle text-info', 'pending' => 'bg-danger-subtle text-danger'][$state],
        'icon' => ['complete' => 'ri-checkbox-circle-line', 'partial' => 'ri-progress-3-line', 'pending' => 'ri-close-circle-line'][$state],
    ];
    $dimensions = [
        $dimension('Cumplimiento', $eventStatus['fulfillment'] ? 'complete' : 'pending'),
        $dimension('Facturación', $eventStatus['invoicing'] ? 'complete' : 'pending'),
        $dimension($eventStatus['payment'] === 'partial' ? 'Pago parcial' : 'Pago', $eventStatus['payment']),
    ];
@endphp
<div class="mb-2">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="fs-12 fw-semibold"><i class="ri-flag-line me-1"></i>Estado del evento</span>
        <span class="badge {{ $eventStatus['class'] }} fs-11">{{ $eventStatus['label'] }}</span>
    </div>
    <div class="d-flex flex-wrap gap-1">
        @foreach ($dimensions as $item)
            <span class="badge {{ $item['class'] }} fs-11"><i class="{{ $item['icon'] }} me-1"></i>{{ $item['label'] }}</span>
        @endforeach
    </div>
    @if ($eventStatus['regularization_pending'])
        <div class="fs-11 text-warning mt-1"><i class="ri-file-warning-line me-1"></i>Pago realizado pendiente de regularización documental (falta factura aceptada).</div>
    @endif
</div>
