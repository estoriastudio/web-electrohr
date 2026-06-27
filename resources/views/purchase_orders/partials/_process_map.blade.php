@php
    $solcom = $purchaseOrder->purchaseRequest ?? null;
    $solmat = $solcom?->materialRequest ?? null;
    $branchOrders = $solcom
        ? ($solcom->purchaseOrders ?? collect())->sortBy('folio')->values()
        : collect();
@endphp

@if ($solcom)
<div class="d-flex align-items-start" style="gap:0;">

    {{-- Nodo SOLMAT --}}
    @if ($solmat)
    <div class="d-flex flex-column align-items-center text-center" style="min-width:54px;">
        <a href="{{ route('material_requests.show', $solmat) }}"
           class="text-decoration-none d-flex flex-column align-items-center" style="gap:4px;">
            <div class="d-flex align-items-center justify-content-center rounded-circle bg-purple-subtle border border-2"
                 style="width:28px;height:28px;border-color:#6f42c1 !important;">
                <i class="ri-hammer-line" style="font-size:12px;color:#6f42c1;"></i>
            </div>
            <span class="fw-medium" style="font-size:10px;white-space:nowrap;color:#6f42c1;">
                SOLMAT #{{ $solmat->folio ?? $solmat->id }}
            </span>
        </a>
    </div>
    {{-- Conector --}}
    <div style="min-width:24px;height:0;border-top:2px dashed #adb5bd;margin-top:13px;flex-shrink:0;"></div>
    @endif

    {{-- Nodo SOLCOM --}}
    <div class="d-flex flex-column align-items-center text-center" style="min-width:58px;">
        <a href="{{ route('purchase_requests.show', $solcom) }}"
           class="text-decoration-none d-flex flex-column align-items-center" style="gap:4px;">
            <div class="d-flex align-items-center justify-content-center rounded-circle bg-warning-subtle border border-warning border-2"
                 style="width:28px;height:28px;">
                <i class="ri-file-text-line text-warning" style="font-size:12px;"></i>
            </div>
            <span class="text-warning fw-medium" style="font-size:10px;white-space:nowrap;">
                SOLCOM #{{ $solcom->folio ?? $solcom->id }}
            </span>
            <span class="text-muted" style="font-size:9px;white-space:nowrap;">
                {{ $branchOrders->count() }} rama(s) OC
            </span>
        </a>
    </div>

    {{-- Rama estilo git: SOLCOM -> múltiples OCs --}}
    <div class="ms-2" style="min-width:200px;">
        @forelse ($branchOrders as $branch)
            @php
                $isCurrent = (int) $branch->id === (int) $purchaseOrder->id;
            @endphp
            <div class="d-flex align-items-center" style="gap:6px;line-height:1.1;">
                <span class="text-muted" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;font-size:11px;">
                    {{ $loop->last ? '\\-' : '|-' }}
                </span>
                @if ($isCurrent)
                    <span class="badge bg-primary text-white" style="font-size:10px;">
                        OC #{{ $branch->folio ?? $branch->id }} (actual)
                    </span>
                @else
                    <a href="{{ route('purchase_orders.show', $branch) }}"
                       class="badge bg-light text-primary border text-decoration-none"
                       style="font-size:10px;">
                        OC #{{ $branch->folio ?? $branch->id }}
                    </a>
                @endif
            </div>
        @empty
            <span class="text-muted" style="font-size:10px;">Sin ramas OC todavía</span>
        @endforelse
    </div>
</div>
@endif