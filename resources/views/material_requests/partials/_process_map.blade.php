@php
    $mapSolcoms = $materialRequest->purchaseRequests ?? collect();
    $firstSolcom  = $mapSolcoms->first();
    $extraSolcoms = max(0, $mapSolcoms->count() - 1);
    $mapOcs       = $firstSolcom ? $firstSolcom->purchaseOrders : collect();
    $firstOc      = $mapOcs->first();
    $extraOcs     = max(0, $mapOcs->count() - 1);

    $user = auth()->user();
    $canOpenOcShow = $user && $user->hasAnyRole(['admin', 'Pagos', 'Orden de compra']);
@endphp

<div class="d-flex align-items-start" style="gap:0;">

    {{-- SOLMAT (documento actual — activo) --}}
    <div class="d-flex flex-column align-items-center text-center" style="min-width:62px;">
        <div class="d-flex flex-column align-items-center" style="gap:4px;">
            <div style="width:28px;height:28px;border-radius:50%;background:#6f42c1;box-shadow:0 0 0 4px rgba(111,66,193,0.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="ri-hammer-line" style="font-size:12px;color:#fff;"></i>
            </div>
            <span style="font-size:10px;white-space:nowrap;color:#6f42c1;font-weight:700;">
                SOLMAT #{{ $materialRequest->folio ?? $materialRequest->id }}
            </span>
        </div>
    </div>

    @if ($firstSolcom)
    {{-- Conector --}}
    <div style="min-width:24px;height:0;border-top:2px dashed #adb5bd;margin-top:13px;flex-shrink:0;"></div>

    {{-- Nodo SOLCOM --}}
    <div class="d-flex flex-column align-items-center text-center" style="min-width:66px;">
        <a href="{{ route('purchase_requests.show', $firstSolcom) }}"
           class="text-decoration-none d-flex flex-column align-items-center" style="gap:4px;">
            <div style="width:28px;height:28px;border-radius:50%;border:2px solid #f59e0b;background:#fff8ed;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="ri-file-text-line" style="font-size:12px;color:#d97706;"></i>
            </div>
            <span style="font-size:10px;color:#d97706;font-weight:500;">
                SOLCOM #{{ $firstSolcom->folio ?? $firstSolcom->id }}
                @if ($extraSolcoms > 0)<br><span style="font-size:9px;">+{{ $extraSolcoms }} más</span>@endif
            </span>
        </a>
    </div>

    @if ($firstOc)
    {{-- Conector --}}
    <div style="min-width:24px;height:0;border-top:2px dashed #adb5bd;margin-top:13px;flex-shrink:0;"></div>

    {{-- Nodo OC --}}
    <div class="d-flex flex-column align-items-center text-center" style="min-width:54px;">
        <a href="{{ $canOpenOcShow ? route('purchase_orders.show', $firstOc) : route('purchase_orders.pdf', $firstOc) }}"
           target="{{ $canOpenOcShow ? '_self' : '_blank' }}"
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
    @endif

</div>