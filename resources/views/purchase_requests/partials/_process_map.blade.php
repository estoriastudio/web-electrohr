@php
    $mapSolmat  = $purchaseRequest->materialRequest ?? null;
    $mapOcs     = $purchaseRequest->purchaseOrders ?? collect();
    $firstOc    = $mapOcs->first();
    $extraOcs   = max(0, $mapOcs->count() - 1);
@endphp

<div class="d-flex align-items-start" style="gap:0;">

    {{-- Nodo SOLMAT (si existe) --}}
    @if ($mapSolmat)
    <div class="d-flex flex-column align-items-center text-center" style="min-width:62px;">
        <a href="{{ route('material_requests.show', $mapSolmat) }}"
           class="text-decoration-none d-flex flex-column align-items-center" style="gap:4px;">
            <div style="width:28px;height:28px;border-radius:50%;border:2px solid #6f42c1;background:#f5f0ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="ri-hammer-line" style="font-size:12px;color:#6f42c1;"></i>
            </div>
            <span style="font-size:10px;white-space:nowrap;color:#6f42c1;font-weight:500;">
                SOLMAT #{{ $mapSolmat->folio ?? $mapSolmat->id }}
            </span>
        </a>
    </div>
    {{-- Conector --}}
    <div style="min-width:24px;height:0;border-top:2px dashed #adb5bd;margin-top:13px;flex-shrink:0;"></div>
    @endif

    {{-- SOLCOM (documento actual — activo) --}}
    <div class="d-flex flex-column align-items-center text-center" style="min-width:66px;">
        <div class="d-flex flex-column align-items-center" style="gap:4px;">
            <div style="width:28px;height:28px;border-radius:50%;background:#d97706;box-shadow:0 0 0 4px rgba(217,119,6,0.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="ri-file-text-line" style="font-size:12px;color:#fff;"></i>
            </div>
            <span style="font-size:10px;white-space:nowrap;color:#d97706;font-weight:700;">
                SOLCOM #{{ $purchaseRequest->folio ?? $purchaseRequest->id }}
            </span>
        </div>
    </div>

    @if ($firstOc)
    {{-- Conector --}}
    <div style="min-width:24px;height:0;border-top:2px dashed #adb5bd;margin-top:13px;flex-shrink:0;"></div>

    {{-- Nodo OC --}}
    <div class="d-flex flex-column align-items-center text-center" style="min-width:54px;">
        <a href="{{ route('purchase_orders.show', $firstOc) }}"
           class="text-decoration-none d-flex flex-column align-items-center" style="gap:4px;">
            <div style="width:28px;height:28px;border-radius:50%;border:2px solid #0d6efd;background:#e8f0ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="ri-file-list-3-line" style="font-size:12px;color:#0d6efd;"></i>
            </div>
            <span style="font-size:10px;color:#0d6efd;font-weight:500;">
                OC #{{ $firstOc->folio ?? $firstOc->id }}
                @if ($extraOcs > 0)<br><span style="font-size:9px;">+{{ $extraOcs }} más</span>@endif
            </span>
        </a>
    </div>
    @endif

</div>