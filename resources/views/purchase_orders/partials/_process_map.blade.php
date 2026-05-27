@php
    $solcom = $purchaseOrder->purchaseRequest ?? null;
    $solmat = $solcom?->materialRequest ?? null;
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
        </a>
    </div>
    {{-- Conector --}}
    <div style="min-width:24px;height:0;border-top:2px dashed #adb5bd;margin-top:13px;flex-shrink:0;"></div>

    {{-- Nodo OC (destino) --}}
    <div class="d-flex flex-column align-items-center text-center" style="min-width:48px;">
        <div class="d-flex flex-column align-items-center" style="gap:4px;">
            <div class="d-flex align-items-center justify-content-center rounded-circle bg-primary border border-primary border-2"
                 style="width:28px;height:28px;">
                <i class="ri-file-list-3-line text-white" style="font-size:12px;"></i>
            </div>
            <span class="text-primary fw-semibold" style="font-size:10px;white-space:nowrap;">
                OC #{{ $purchaseOrder->folio ?? $purchaseOrder->id }}
            </span>
        </div>
    </div>
</div>
@endif