@if ($applicable > 0)
    <span class="badge bg-{{ $uploaded === $applicable ? 'success' : 'secondary' }}-subtle text-{{ $uploaded === $applicable ? 'success' : 'secondary' }} ms-2 fw-normal">
        {{ $uploaded }}/{{ $applicable }}
    </span>
@endif
