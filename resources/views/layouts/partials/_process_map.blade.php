@php
    $user = auth()->user();
    $canOpenOcShow = $user && $user->hasAnyRole(['admin', 'Pagos', 'Orden de compra']);

    $currentType = null;
    $currentId = null;

    $mapSolmats = collect();
    $fallbackSolcoms = collect();

    if (isset($materialRequest) && $materialRequest) {
        $currentType = 'solmat';
        $currentId = (int) $materialRequest->id;
        $mapSolmats = collect([$materialRequest]);
    } elseif (isset($purchaseRequest) && $purchaseRequest) {
        $currentType = 'solcom';
        $currentId = (int) $purchaseRequest->id;

        $mapSolmats = ($purchaseRequest->materialRequests ?? collect())
            ->sortBy('folio')
            ->values();

        if ($mapSolmats->isEmpty() && $purchaseRequest->materialRequest) {
            $mapSolmats = collect([$purchaseRequest->materialRequest]);
        }

        $fallbackSolcoms = collect([$purchaseRequest]);
    } elseif (isset($purchaseOrder) && $purchaseOrder) {
        $currentType = 'oc';
        $currentId = (int) $purchaseOrder->id;

        $orderSolcom = $purchaseOrder->purchaseRequest ?? null;

        if ($orderSolcom) {
            $mapSolmats = ($orderSolcom->materialRequests ?? collect())
                ->sortBy('folio')
                ->values();

            if ($mapSolmats->isEmpty() && $orderSolcom->materialRequest) {
                $mapSolmats = collect([$orderSolcom->materialRequest]);
            }

            $fallbackSolcoms = collect([$orderSolcom]);
        }
    }

    $mapSolmats = $mapSolmats->filter()->sortBy('folio')->values();

    $resolveSolcoms = function ($solmat) {
        return ($solmat->purchaseRequests ?? collect())
            ->sortBy('folio')
            ->values();
    };

    $resolveOcs = function ($solcom) {
        return ($solcom->purchaseOrders ?? collect())
            ->sortBy('folio')
            ->values();
    };
@endphp

@once
@push('styles')
<style>
.process-tree .ul-tree.horizontal ul {
    position: relative;
    margin: 0;
    padding: 0 0 0 .9rem;
    list-style: none;
}
.process-tree .ul-tree.horizontal ul:before {
    position: absolute;
    content: "";
    top: 50%;
    left: 0;
    width: .9rem;
    border-top: 1px dashed #adb5bd;
}
.process-tree .ul-tree.horizontal li {
    position: relative;
    display: flex;
    align-items: center;
    padding: 0 0 0 .9rem;
}
.process-tree .ul-tree.horizontal li:before {
    position: absolute;
    content: "";
    top: 0;
    left: 0;
    bottom: 0;
    border-left: 1px dashed #adb5bd;
}
.process-tree .ul-tree.horizontal li:first-child:before { top: 50%; }
.process-tree .ul-tree.horizontal li:last-child:before { bottom: 50%; }
.process-tree .ul-tree.horizontal > ul { padding: 0; }
.process-tree .ul-tree.horizontal > ul:before,
.process-tree .ul-tree.horizontal > ul > li:before { display: none; }
.process-tree .process-node {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    margin: .32rem 0;
    padding: .25rem .48rem;
    border-radius: .55rem;
    border: 1px solid transparent;
    font-size: 10px;
    line-height: 1.15;
    text-decoration: none;
    white-space: nowrap;
}
.process-tree .process-node i { font-size: 11px; }
.process-tree .node-solmat { background: #f8f3ff; color: #6f42c1; border-color: #dacbfd; }
.process-tree .node-solcom { background: #fff8ed; color: #d97706; border-color: #ffd9a2; }
.process-tree .node-oc { background: #e8f0ff; color: #0d6efd; border-color: #bdd3ff; }
.process-tree .node-branch { background: #f8f9fa; color: #495057; border-color: #d7dbe0; }
.process-tree .is-current {
    font-weight: 700;
    box-shadow: 0 0 0 3px rgba(13,110,253,.14);
}
</style>
@endpush
@endonce

<div class="process-tree">
    <div class="ul-tree horizontal">
        <ul>
            <li>
                @if ($mapSolmats->isEmpty())
                    <span class="process-node node-branch">
                        <i class="ri-information-line"></i>
                        Sin origen SOLMAT
                    </span>

                    @if ($fallbackSolcoms->isNotEmpty())
                        <ul>
                            <li>
                                @if ($fallbackSolcoms->count() === 1)
                                    @php
                                        $singleSolcom = $fallbackSolcoms->first();
                                        $singleSolcomOcs = $resolveOcs($singleSolcom);
                                        $isCurrentSolcom = $currentType === 'solcom' && (int) $singleSolcom->id === $currentId;
                                    @endphp

                                    @if ($isCurrentSolcom)
                                        <span class="process-node node-solcom is-current">
                                            <i class="ri-file-text-line"></i>
                                            SOLCOM #{{ $singleSolcom->folio ?? $singleSolcom->id }}
                                        </span>
                                    @else
                                        <a href="{{ route('purchase_requests.show', $singleSolcom) }}" class="process-node node-solcom">
                                            <i class="ri-file-text-line"></i>
                                            SOLCOM #{{ $singleSolcom->folio ?? $singleSolcom->id }}
                                        </a>
                                    @endif

                                    @if ($singleSolcomOcs->isNotEmpty())
                                        <ul>
                                            @foreach ($singleSolcomOcs as $mapOc)
                                                @php
                                                    $isCurrentOc = $currentType === 'oc' && (int) $mapOc->id === $currentId;
                                                @endphp
                                                <li>
                                                    @if ($isCurrentOc)
                                                        <span class="process-node node-oc is-current">
                                                            <i class="ri-file-list-3-line"></i>
                                                            OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                        </span>
                                                    @else
                                                        <a href="{{ $canOpenOcShow ? route('purchase_orders.show', $mapOc) : route('purchase_orders.pdf', $mapOc) }}"
                                                           target="{{ $canOpenOcShow ? '_self' : '_blank' }}"
                                                           class="process-node node-oc">
                                                            <i class="ri-file-list-3-line"></i>
                                                            OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                        </a>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @else
                                    <span class="process-node node-branch">
                                        <i class="ri-git-branch-line"></i>
                                        Ramas SOLCOM ({{ $fallbackSolcoms->count() }})
                                    </span>
                                    <ul>
                                        @foreach ($fallbackSolcoms as $mapSolcom)
                                            @php
                                                $mapSolcomOcs = $resolveOcs($mapSolcom);
                                                $isCurrentSolcom = $currentType === 'solcom' && (int) $mapSolcom->id === $currentId;
                                            @endphp
                                            <li>
                                                @if ($isCurrentSolcom)
                                                    <span class="process-node node-solcom is-current">
                                                        <i class="ri-file-text-line"></i>
                                                        SOLCOM #{{ $mapSolcom->folio ?? $mapSolcom->id }}
                                                    </span>
                                                @else
                                                    <a href="{{ route('purchase_requests.show', $mapSolcom) }}" class="process-node node-solcom">
                                                        <i class="ri-file-text-line"></i>
                                                        SOLCOM #{{ $mapSolcom->folio ?? $mapSolcom->id }}
                                                    </a>
                                                @endif

                                                @if ($mapSolcomOcs->isNotEmpty())
                                                    <ul>
                                                        @foreach ($mapSolcomOcs as $mapOc)
                                                            @php
                                                                $isCurrentOc = $currentType === 'oc' && (int) $mapOc->id === $currentId;
                                                            @endphp
                                                            <li>
                                                                @if ($isCurrentOc)
                                                                    <span class="process-node node-oc is-current">
                                                                        <i class="ri-file-list-3-line"></i>
                                                                        OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                                    </span>
                                                                @else
                                                                    <a href="{{ $canOpenOcShow ? route('purchase_orders.show', $mapOc) : route('purchase_orders.pdf', $mapOc) }}"
                                                                       target="{{ $canOpenOcShow ? '_self' : '_blank' }}"
                                                                       class="process-node node-oc">
                                                                        <i class="ri-file-list-3-line"></i>
                                                                        OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                                    </a>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        </ul>
                    @endif
                @elseif ($mapSolmats->count() === 1)
                    @php
                        $singleSolmat = $mapSolmats->first();
                        $mapSolcoms = $resolveSolcoms($singleSolmat);
                        $isCurrentSolmat = $currentType === 'solmat' && (int) $singleSolmat->id === $currentId;
                    @endphp

                    @if ($isCurrentSolmat)
                        <span class="process-node node-solmat is-current">
                            <i class="ri-hammer-line"></i>
                            SOLMAT #{{ $singleSolmat->folio ?? $singleSolmat->id }}
                        </span>
                    @else
                        <a href="{{ route('material_requests.show', $singleSolmat) }}" class="process-node node-solmat">
                            <i class="ri-hammer-line"></i>
                            SOLMAT #{{ $singleSolmat->folio ?? $singleSolmat->id }}
                        </a>
                    @endif

                    @if ($mapSolcoms->isNotEmpty())
                        <ul>
                            <li>
                                @if ($mapSolcoms->count() === 1)
                                    @php
                                        $singleSolcom = $mapSolcoms->first();
                                        $singleSolcomOcs = $resolveOcs($singleSolcom);
                                        $isCurrentSolcom = $currentType === 'solcom' && (int) $singleSolcom->id === $currentId;
                                    @endphp

                                    @if ($isCurrentSolcom)
                                        <span class="process-node node-solcom is-current">
                                            <i class="ri-file-text-line"></i>
                                            SOLCOM #{{ $singleSolcom->folio ?? $singleSolcom->id }}
                                        </span>
                                    @else
                                        <a href="{{ route('purchase_requests.show', $singleSolcom) }}" class="process-node node-solcom">
                                            <i class="ri-file-text-line"></i>
                                            SOLCOM #{{ $singleSolcom->folio ?? $singleSolcom->id }}
                                        </a>
                                    @endif

                                    @if ($singleSolcomOcs->isNotEmpty())
                                        <ul>
                                            @if ($singleSolcomOcs->count() === 1)
                                                @php
                                                    $singleOc = $singleSolcomOcs->first();
                                                    $isCurrentOc = $currentType === 'oc' && (int) $singleOc->id === $currentId;
                                                @endphp
                                                <li>
                                                    @if ($isCurrentOc)
                                                        <span class="process-node node-oc is-current">
                                                            <i class="ri-file-list-3-line"></i>
                                                            OC #{{ $singleOc->folio ?? $singleOc->id }}
                                                        </span>
                                                    @else
                                                        <a href="{{ $canOpenOcShow ? route('purchase_orders.show', $singleOc) : route('purchase_orders.pdf', $singleOc) }}"
                                                           target="{{ $canOpenOcShow ? '_self' : '_blank' }}"
                                                           class="process-node node-oc">
                                                            <i class="ri-file-list-3-line"></i>
                                                            OC #{{ $singleOc->folio ?? $singleOc->id }}
                                                        </a>
                                                    @endif
                                                </li>
                                            @else
                                                <li>
                                                    <span class="process-node node-branch">
                                                        <i class="ri-git-branch-line"></i>
                                                        Ramas OC ({{ $singleSolcomOcs->count() }})
                                                    </span>
                                                    <ul>
                                                        @foreach ($singleSolcomOcs as $mapOc)
                                                            @php
                                                                $isCurrentOc = $currentType === 'oc' && (int) $mapOc->id === $currentId;
                                                            @endphp
                                                            <li>
                                                                @if ($isCurrentOc)
                                                                    <span class="process-node node-oc is-current">
                                                                        <i class="ri-file-list-3-line"></i>
                                                                        OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                                    </span>
                                                                @else
                                                                    <a href="{{ $canOpenOcShow ? route('purchase_orders.show', $mapOc) : route('purchase_orders.pdf', $mapOc) }}"
                                                                       target="{{ $canOpenOcShow ? '_self' : '_blank' }}"
                                                                       class="process-node node-oc">
                                                                        <i class="ri-file-list-3-line"></i>
                                                                        OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                                    </a>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </li>
                                            @endif
                                        </ul>
                                    @endif
                                @else
                                    <span class="process-node node-branch">
                                        <i class="ri-git-branch-line"></i>
                                        Ramas SOLCOM ({{ $mapSolcoms->count() }})
                                    </span>
                                    <ul>
                                        @foreach ($mapSolcoms as $mapSolcom)
                                            @php
                                                $mapSolcomOcs = $resolveOcs($mapSolcom);
                                                $isCurrentSolcom = $currentType === 'solcom' && (int) $mapSolcom->id === $currentId;
                                            @endphp
                                            <li>
                                                @if ($isCurrentSolcom)
                                                    <span class="process-node node-solcom is-current">
                                                        <i class="ri-file-text-line"></i>
                                                        SOLCOM #{{ $mapSolcom->folio ?? $mapSolcom->id }}
                                                    </span>
                                                @else
                                                    <a href="{{ route('purchase_requests.show', $mapSolcom) }}" class="process-node node-solcom">
                                                        <i class="ri-file-text-line"></i>
                                                        SOLCOM #{{ $mapSolcom->folio ?? $mapSolcom->id }}
                                                    </a>
                                                @endif

                                                @if ($mapSolcomOcs->isNotEmpty())
                                                    <ul>
                                                        @foreach ($mapSolcomOcs as $mapOc)
                                                            @php
                                                                $isCurrentOc = $currentType === 'oc' && (int) $mapOc->id === $currentId;
                                                            @endphp
                                                            <li>
                                                                @if ($isCurrentOc)
                                                                    <span class="process-node node-oc is-current">
                                                                        <i class="ri-file-list-3-line"></i>
                                                                        OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                                    </span>
                                                                @else
                                                                    <a href="{{ $canOpenOcShow ? route('purchase_orders.show', $mapOc) : route('purchase_orders.pdf', $mapOc) }}"
                                                                       target="{{ $canOpenOcShow ? '_self' : '_blank' }}"
                                                                       class="process-node node-oc">
                                                                        <i class="ri-file-list-3-line"></i>
                                                                        OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                                    </a>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        </ul>
                    @endif
                @else
                    <span class="process-node node-branch">
                        <i class="ri-git-merge-line"></i>
                        Ramas SOLMAT ({{ $mapSolmats->count() }})
                    </span>
                    <ul>
                        @foreach ($mapSolmats as $mapSolmat)
                            @php
                                $mapSolcoms = $resolveSolcoms($mapSolmat);
                                $isCurrentSolmat = $currentType === 'solmat' && (int) $mapSolmat->id === $currentId;
                            @endphp
                            <li>
                                @if ($isCurrentSolmat)
                                    <span class="process-node node-solmat is-current">
                                        <i class="ri-hammer-line"></i>
                                        SOLMAT #{{ $mapSolmat->folio ?? $mapSolmat->id }}
                                    </span>
                                @else
                                    <a href="{{ route('material_requests.show', $mapSolmat) }}" class="process-node node-solmat">
                                        <i class="ri-hammer-line"></i>
                                        SOLMAT #{{ $mapSolmat->folio ?? $mapSolmat->id }}
                                    </a>
                                @endif

                                @if ($mapSolcoms->isNotEmpty())
                                    <ul>
                                        @foreach ($mapSolcoms as $mapSolcom)
                                            @php
                                                $mapSolcomOcs = $resolveOcs($mapSolcom);
                                                $isCurrentSolcom = $currentType === 'solcom' && (int) $mapSolcom->id === $currentId;
                                            @endphp
                                            <li>
                                                @if ($isCurrentSolcom)
                                                    <span class="process-node node-solcom is-current">
                                                        <i class="ri-file-text-line"></i>
                                                        SOLCOM #{{ $mapSolcom->folio ?? $mapSolcom->id }}
                                                    </span>
                                                @else
                                                    <a href="{{ route('purchase_requests.show', $mapSolcom) }}" class="process-node node-solcom">
                                                        <i class="ri-file-text-line"></i>
                                                        SOLCOM #{{ $mapSolcom->folio ?? $mapSolcom->id }}
                                                    </a>
                                                @endif

                                                @if ($mapSolcomOcs->isNotEmpty())
                                                    <ul>
                                                        @foreach ($mapSolcomOcs as $mapOc)
                                                            @php
                                                                $isCurrentOc = $currentType === 'oc' && (int) $mapOc->id === $currentId;
                                                            @endphp
                                                            <li>
                                                                @if ($isCurrentOc)
                                                                    <span class="process-node node-oc is-current">
                                                                        <i class="ri-file-list-3-line"></i>
                                                                        OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                                    </span>
                                                                @else
                                                                    <a href="{{ $canOpenOcShow ? route('purchase_orders.show', $mapOc) : route('purchase_orders.pdf', $mapOc) }}"
                                                                       target="{{ $canOpenOcShow ? '_self' : '_blank' }}"
                                                                       class="process-node node-oc">
                                                                        <i class="ri-file-list-3-line"></i>
                                                                        OC #{{ $mapOc->folio ?? $mapOc->id }}
                                                                    </a>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        </ul>
    </div>
</div>
