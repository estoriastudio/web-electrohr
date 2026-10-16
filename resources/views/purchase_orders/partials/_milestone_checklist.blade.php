{{-- Checklist de comprobación de un hito. Requiere $checklist (PurchaseOrderMilestoneChecklist). --}}
@php $compact = $compact ?? false; @endphp
<div class="milestone-checklist {{ $compact ? 'd-flex flex-wrap gap-1' : '' }}">
    @foreach ($checklist['requirements'] as $requirement)
        @php
            $state = !$requirement['required'] ? 'na' : ($requirement['met'] ? 'ok' : 'pending');
            $badgeClass = ['ok' => 'bg-success-subtle text-success', 'pending' => 'bg-danger-subtle text-danger', 'na' => 'bg-light text-muted'][$state];
            $icon = ['ok' => 'ri-checkbox-circle-line', 'pending' => 'ri-close-circle-line', 'na' => 'ri-subtract-line'][$state];
        @endphp
        @if ($compact)
            <span class="badge {{ $badgeClass }} fs-11" title="{{ $requirement['detail'] }}">
                <i class="{{ $icon }} me-1"></i>{{ $requirement['label'] }}
            </span>
        @else
            <div class="d-flex justify-content-between align-items-start mb-1">
                <span class="fs-12 text-muted"><i class="{{ $icon }} me-1 {{ $state === 'ok' ? 'text-success' : ($state === 'pending' ? 'text-danger' : '') }}"></i>{{ $requirement['label'] }}</span>
                <span class="fs-12 text-end {{ $state === 'ok' ? 'text-success' : ($state === 'pending' ? 'text-danger' : 'text-muted') }}">{{ $requirement['detail'] }}</span>
            </div>
        @endif
    @endforeach
</div>
