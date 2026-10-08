@extends('layouts.app')

@section('page_title', 'Estado de Cuenta de Proveedores')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Proveedores</a></li>
    <li class="breadcrumb-item active">Estado de Cuenta</li>
@endsection

@push('styles')
<style>
    .statement-metric { min-height: 106px; }
    .statement-metric .metric-value { font-size: 1.15rem; overflow-wrap: anywhere; }
    .statement-review-link .statement-review-label { text-decoration: underline; text-underline-offset: 3px; }
    .statement-review-link:hover { border-color: var(--bs-primary); }
    .statement-review-link:focus-visible { outline: 2px solid var(--bs-primary); outline-offset: 2px; }
    .statement-status { display: inline-block; min-height: 30px; padding: .35rem .6rem; border: 1px solid transparent; border-radius: 6px; font-size: 12px; font-weight: 600; letter-spacing: 0; white-space: nowrap; line-height: 1.35; text-align: left; }
    .statement-status i { display: inline-block; width: 16px; margin-right: .35rem; font-size: 16px; line-height: 1; vertical-align: -2px; }
    .statement-status--pendiente { color: var(--bs-warning-text-emphasis, #664d03); background: var(--bs-warning-bg-subtle, #fff3cd); border-color: var(--bs-warning-border-subtle, #ffe69c); }
    .statement-status--pagado { color: var(--bs-success-text-emphasis, #0a3622); background: var(--bs-success-bg-subtle, #d1e7dd); border-color: var(--bs-success-border-subtle, #a3cfbb); }
    .statement-status--vencido { color: var(--bs-danger-text-emphasis, #58151c); background: var(--bs-danger-bg-subtle, #f8d7da); border-color: var(--bs-danger-border-subtle, #f1aeb5); }
    .statement-status--sin_factura { color: var(--bs-info-text-emphasis, #055160); background: var(--bs-info-bg-subtle, #cff4fc); border-color: var(--bs-info-border-subtle, #9eeaf9); }
    .statement-status--conciliacion { color: var(--bs-secondary-text-emphasis, #41464b); background: var(--bs-secondary-bg-subtle, #e2e3e5); border-color: var(--bs-secondary-border-subtle, #c4c8cb); }
    .statement-order-link { text-decoration: underline; text-underline-offset: 3px; }
    .statement-order-link:hover { text-decoration: underline; text-decoration-thickness: 2px; }
    .statement-order-link:focus-visible { outline: 2px solid var(--bs-primary); outline-offset: 3px; }
    .statement-name { min-width: 140px; max-width: 220px; white-space: normal; overflow-wrap: anywhere; }
    .statement-folio { max-width: 180px; white-space: normal; overflow-wrap: anywhere; }
    .statement-summary th, .statement-summary td { white-space: normal; }
    .statement-search { position: relative; }
    .statement-search .twitter-typeahead { display: block !important; width: 100%; }
    .statement-search .form-control { padding-left: 1.8rem; padding-right: 2rem; }
    .statement-search-icon { position: absolute; left: .6rem; top: 50%; transform: translateY(-50%); z-index: 1; pointer-events: none; }
    .statement-search-clear { position: absolute; right: .25rem; top: 50%; transform: translateY(-50%); z-index: 1; width: 26px; height: 26px; padding: 0; }
    .statement-search .tt-menu { width: 100%; max-height: 280px; overflow-y: auto; background: var(--bs-body-bg); color: var(--bs-body-color); border: 1px solid var(--bs-border-color); border-radius: 6px; margin-top: 4px; padding: .3rem 0; box-shadow: 0 4px 14px rgba(0,0,0,.08); z-index: var(--bs-dropdown-zindex, 1000); }
    .statement-search .tt-suggestion { padding: .55rem .7rem; font-size: 13px; white-space: normal; overflow-wrap: anywhere; cursor: pointer; }
    .statement-search .tt-suggestion:hover, .statement-search .tt-cursor { color: var(--bs-primary-text-emphasis, #084298); background: var(--bs-primary-bg-subtle, #cfe2ff); }
    .statement-search .tt-empty { padding: .6rem .7rem; font-size: 12px; color: var(--bs-secondary-color); }
</style>
@endpush

@section('content')
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('suppliers.account_statement.index') }}" class="row g-2 align-items-end" id="statementFilters">
            <div class="col-12 col-md-6 col-xl-2">
                <label for="statementSupplier" class="form-label fs-12">Proveedor</label>
                <select name="supplier_id" id="statementSupplier" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" data-search="{{ $supplier->commercial_name }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->rfc_name ?: $supplier->commercial_name }}</option>
                    @endforeach
                </select>
                <div class="statement-search d-none" id="statementSupplierSearchGroup">
                    <i class="ri-search-line text-muted statement-search-icon" aria-hidden="true"></i>
                    <input type="text" id="statementSupplierSearch" class="form-control form-control-sm" placeholder="Buscar proveedor" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false">
                    <button type="button" class="btn btn-light btn-sm statement-search-clear d-none" aria-label="Quitar proveedor" title="Quitar proveedor"><i class="ri-close-line" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label for="statementProject" class="form-label fs-12">Proyecto</label>
                <select name="project_id" id="statementProject" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected(($filters['project_id'] ?? '') == $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
                <div class="statement-search d-none" id="statementProjectSearchGroup">
                    <i class="ri-search-line text-muted statement-search-icon" aria-hidden="true"></i>
                    <input type="text" id="statementProjectSearch" class="form-control form-control-sm" placeholder="Buscar proyecto" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false">
                    <button type="button" class="btn btn-light btn-sm statement-search-clear d-none" aria-label="Quitar proyecto" title="Quitar proyecto"><i class="ri-close-line" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label for="statementOrder" class="form-label fs-12">Orden de Compra</label>
                <input name="order" id="statementOrder" value="{{ $filters['order'] ?? '' }}" class="form-control form-control-sm" maxlength="100" placeholder="Numero de OC" autocomplete="off">
            </div>
            <div class="col-6 col-md-3 col-xl-1">
                <label for="statementCurrency" class="form-label fs-12">Moneda</label>
                <select name="currency" id="statementCurrency" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach(['MXN', 'USD', 'EUR'] as $currency)
                        <option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="statementBuyer" class="form-label fs-12">Usuario responsable</label>
                <select name="buyer_id" id="statementBuyer" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($buyers as $buyer)
                        <option value="{{ $buyer->id }}" @selected(($filters['buyer_id'] ?? '') == $buyer->id)>{{ $buyer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label for="statementStatus" class="form-label fs-12">Estatus</label>
                <div class="d-flex gap-2">
                    <select name="status" id="statementStatus" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ ['sin_factura' => 'Pagado sin factura', 'conciliacion' => 'Por revisar'][$value] ?? $label }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary btn-sm flex-shrink-0" type="submit" title="Buscar" aria-label="Buscar"><i class="ri-search-line"></i></button>
                    <a class="btn btn-light btn-sm flex-shrink-0" href="{{ route('suppliers.account_statement.index') }}" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="ri-filter-off-line"></i></a>
                </div>
            </div>
            <input type="hidden" name="per_page" value="{{ $rows->perPage() }}">
        </form>
    </div>
</div>

@if(($filters['status'] ?? '') === 'conciliacion')
    @include('suppliers.partials._account_statement_review_help')
@endif

@foreach($summary as $currency => $totals)
    <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
        <span class="fw-semibold">{{ $currency }}</span>
        <span class="text-muted fs-12">{{ $totals['invoices'] }} facturas / {{ $totals['records'] - $totals['invoices'] }} pagos sin aplicar</span>
    </div>
    @if(!$totals['complete'])
        <div class="alert alert-warning py-2 fs-13" role="alert">
            <i class="ri-alert-line me-1" aria-hidden="true"></i>Algunas facturas necesitan revision. Sus saldos no se incluyen en los totales de pendiente y vencido. <a href="{{ route('suppliers.account_statement.index', array_merge($filters, ['status' => 'conciliacion', 'currency' => $currency, 'per_page' => $rows->perPage()])) }}" class="alert-link">Ver por que y como resolverlo</a>
        </div>
    @endif
    <div class="row g-2 mb-3">
        @foreach([
            ['invoiced', 'Total facturado', 'ri-file-list-3-line', 'primary'],
            ['paid', 'Total pagado', 'ri-bank-card-line', 'success'],
            ['pending', $totals['complete'] ? 'Pendiente de pago' : 'Pendiente confirmado', 'ri-time-line', 'warning'],
            ['overdue', $totals['complete'] ? 'Vencido' : 'Vencido confirmado', 'ri-alarm-warning-line', 'danger'],
            ['unregularized', 'Pagos sin factura', 'ri-file-warning-line', 'warning'],
            ['unreconciled', 'Pagos por revisar', 'ri-links-line', 'secondary'],
        ] as [$field, $label, $icon, $color])
            <div class="col-6 col-md-4 col-xl-2">
                @if($field === 'unreconciled')
                <a href="{{ route('suppliers.account_statement.index', array_merge($filters, ['status' => 'conciliacion', 'currency' => $currency, 'per_page' => $rows->perPage()])) }}"
                   class="card statement-metric statement-review-link h-100 mb-0 text-reset text-decoration-none"
                   aria-label="Ver pagos por revisar en {{ $currency }}">
                @else
                <div class="card statement-metric h-100 mb-0">
                @endif
                    <div class="card-body p-3">
                        <div class="d-flex gap-2 align-items-start">
                            <i class="{{ $icon }} text-{{ $color }} fs-20 flex-shrink-0"></i>
                            <div class="min-w-0">
                                <div class="text-muted fs-12 mb-1 {{ $field === 'unreconciled' ? 'statement-review-label' : '' }}">{{ $label }} @if($field === 'unreconciled')<i class="ri-arrow-right-up-line" aria-hidden="true"></i>@endif</div>
                                <div class="fw-semibold metric-value {{ $field === 'overdue' ? 'text-danger' : '' }}">$ {{ number_format($totals[$field] / 100, 2) }}</div>
                                <div class="text-muted fs-11">{{ $currency }}</div>
                            </div>
                        </div>
                    </div>
                @if($field === 'unreconciled')
                </a>
                @else
                </div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <h4 class="card-title mb-0">Facturas y pagos</h4>
            <span class="badge bg-secondary-subtle text-secondary">{{ $rows->total() }} registros</span>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('suppliers.account_statement.export', $filters) }}"><i class="ri-download-2-line me-1"></i>Exportar Excel</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle text-nowrap table-hover table-centered mb-0 app-list-table">
                <thead class="bg-light-subtle">
                    <tr><th>Estatus</th><th>Orden de Compra</th><th>Proveedor</th><th>Proyecto</th><th>Factura</th><th>Fecha factura</th><th>Vencimiento</th><th>Moneda</th><th class="text-end">Importe factura</th><th class="text-end">Pagado</th><th class="text-end">Saldo pendiente</th><th>Responsable</th><th>Documentos</th></tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>@include('suppliers.partials._account_statement_status', ['status' => $row['status']])</td>
                            <td><button class="btn btn-link btn-sm p-0 fw-semibold text-start statement-folio statement-order-link" type="button" data-bs-toggle="modal" data-bs-target="#statementOrderModal" data-summary-url="{{ route('suppliers.account_statement.order', $row['order_id']) }}" data-order-folio="{{ $row['order_folio'] }}" aria-label="Ver resumen de la OC #{{ ltrim($row['order_folio'], '#') }}">#{{ ltrim($row['order_folio'], '#') }}</button></td>
                            <td>
                                <div class="hover-marquee" style="--marquee-width:220px;" title="{{ $row['supplier'] }}">
                                    <span class="track"><span>{{ $row['supplier'] }}</span><span aria-hidden="true">{{ $row['supplier'] }}</span></span>
                                </div>
                            </td>
                            <td>
                                <div class="hover-marquee" style="--marquee-width:180px;" title="{{ $row['project'] }}">
                                    <span class="track"><span>{{ $row['project'] }}</span><span aria-hidden="true">{{ $row['project'] }}</span></span>
                                </div>
                            </td>
                            <td>
                                @if($row['invoice_id'])
                                    <a href="{{ route('invoices.show', $row['invoice_id']) }}" target="_blank" rel="noopener" class="d-block hover-marquee" style="--marquee-width:180px;" title="{{ $row['folio'] }}">
                                        <span class="track"><span>{{ $row['folio'] }}</span><span aria-hidden="true">{{ $row['folio'] }}</span></span>
                                    </a>
                                @else
                                    <span class="text-muted">{{ $row['status'] === 'conciliacion' ? 'Pago por revisar' : 'Sin factura' }}</span><div class="fs-11 text-muted">Pago #{{ $row['payment_id'] }} / {{ $row['payment_date'] ?: 'Sin fecha' }}</div>
                                @endif
                            </td>
                            <td>{{ $row['issue_date'] ?: '-' }}</td>
                            <td class="{{ $row['status'] === 'vencido' ? 'text-danger' : '' }}">{{ $row['due_date'] ?: '-' }}</td>
                            <td>{{ $row['currency'] }}</td>
                            <td class="text-end" title="{{ $row['type'] === 'invoice' ? 'Total fiscal: ' . number_format($row['gross'] / 100, 2) . ' / Nota de credito: ' . number_format($row['credit_note'] / 100, 2) : '' }}">{{ $row['type'] === 'invoice' ? '$ ' . number_format($row['invoiced'] / 100, 2) : '-' }}</td>
                            <td class="text-end">$ {{ number_format($row['paid'] / 100, 2) }}</td>
                            <td class="text-end {{ $row['balance'] ? 'text-danger' : '' }}">{{ $row['balance'] === null ? ($row['type'] === 'invoice' ? 'Por revisar' : '-') : '$ ' . number_format($row['balance'] / 100, 2) }}</td>
                            <td><div class="statement-name">{{ $row['buyer'] }}</div></td>
                            <td>
                                @forelse($row['receipt_payment_ids'] as $paymentId)
                                    <a class="btn btn-light btn-sm" href="{{ route('payments.spei_receipt.download', ['payment' => $paymentId, 'disposition' => 'inline']) }}" target="_blank" rel="noopener" title="SPEI del pago #{{ $paymentId }}" aria-label="SPEI del pago #{{ $paymentId }}"><i class="ri-bank-line"></i></a>
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="text-center text-muted py-4"><i class="ri-file-search-line fs-24 d-block mb-1"></i>No hay facturas ni pagos para estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <span class="fs-12 text-muted">{{ $rows->firstItem() ?? 0 }} a {{ $rows->lastItem() ?? 0 }} de {{ $rows->total() }}</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{ $rows->links('pagination::bootstrap-5') }}
            <form method="GET" action="{{ route('suppliers.account_statement.index') }}">
                @foreach($filters as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                <select name="per_page" class="form-select form-select-sm" aria-label="Registros por pagina" onchange="this.form.requestSubmit()">
                    @foreach([25, 50, 100] as $size)<option value="{{ $size }}" @selected($rows->perPage() === $size)>{{ $size }} / pagina</option>@endforeach
                </select>
            </form>
        </div>
    </div>
</div>

@if($summary)
<div class="mt-3">
    <h5 class="fs-14 mb-2">Desglose por estatus</h5>
    <div class="table-responsive">
        <table class="table table-sm statement-summary mb-0">
            <thead class="bg-light-subtle"><tr><th>Moneda</th><th>Estatus</th><th class="text-end">Registros</th><th class="text-end">Saldo por cubrir</th><th class="text-end">Importe cubierto</th></tr></thead>
            <tbody>
                @foreach($summary as $currency => $totals)
                    @foreach($totals['statuses'] as $status => $detail)
                        @if($detail['count'])
                            <tr><td>{{ $currency }}</td><td>@include('suppliers.partials._account_statement_status', ['status' => $status])</td><td class="text-end">{{ $detail['count'] }}</td><td class="text-end">{{ in_array($status, ['pendiente', 'vencido']) ? '$ ' . number_format($detail['amount'] / 100, 2) : '-' }}</td><td class="text-end">{{ !in_array($status, ['pendiente', 'vencido']) ? '$ ' . number_format($detail['amount'] / 100, 2) : '-' }}</td></tr>
                        @endif
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="modal fade" id="statementOrderModal" tabindex="-1" aria-labelledby="statementOrderTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="statementOrderTitle">Resumen de Orden de Compra</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body" id="statementOrderBody" aria-live="polite"></div>
            <div class="modal-footer"><button class="btn btn-light btn-sm" type="button" data-bs-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/typeahead.js/typeahead.bundle.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filters = document.getElementById('statementFilters');
    filters.querySelectorAll('select').forEach(select => select.addEventListener('change', () => filters.requestSubmit()));

    function setupSearch(selectId, placeholder) {
        if (!window.jQuery || !window.jQuery.fn.typeahead) return;
        const select = document.getElementById(selectId);
        const input = document.getElementById(selectId + 'Search');
        const group = document.getElementById(selectId + 'SearchGroup');
        const clear = group.querySelector('button');
        const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
        const options = Array.from(select.options).map(option => ({
            id: option.value,
            name: option.value ? option.textContent.trim() : placeholder,
            search: normalize(option.textContent + ' ' + (option.dataset.search || '')),
        }));
        const field = window.jQuery(input);
        const selected = options.find(option => option.id === select.value);
        input.value = select.value ? selected.name : '';
        const refreshClear = () => clear.classList.toggle('d-none', !input.value);

        field.typeahead({ hint: false, highlight: true, minLength: 0 }, {
            name: selectId,
            display: 'name',
            limit: 12,
            source: (query, sync) => {
                const words = normalize(query).split(/\s+/).filter(Boolean);
                sync(options.filter(option => !words.length || (option.id && words.every(word => option.search.includes(word)))).slice(0, 12));
            },
            templates: {
                suggestion: option => window.jQuery('<div>').text(option.name),
                notFound: '<div class="tt-empty">Sin resultados</div>',
            },
        });
        const menu = group.querySelector('.tt-menu');
        menu.id = input.id + 'Results';
        menu.setAttribute('role', 'listbox');
        input.setAttribute('aria-controls', menu.id);
        field.on('typeahead:open', () => input.setAttribute('aria-expanded', 'true'));
        field.on('typeahead:close', () => {
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        });
        field.on('typeahead:render', () => {
            menu.querySelectorAll('.tt-suggestion').forEach((suggestion, index) => {
                suggestion.id = menu.id + '-' + index;
                suggestion.setAttribute('role', 'option');
            });
        });
        field.on('typeahead:cursorchange', () => {
            const cursor = menu.querySelector('.tt-cursor');
            if (cursor) input.setAttribute('aria-activedescendant', cursor.id);
            else input.removeAttribute('aria-activedescendant');
        });
        field.on('typeahead:select typeahead:autocomplete', (event, option) => {
            select.value = option.id;
            input.setCustomValidity('');
            if (!option.id) field.typeahead('val', '');
            refreshClear();
            select.dispatchEvent(new Event('change'));
        });
        input.addEventListener('input', () => {
            select.value = '';
            input.setCustomValidity('');
            refreshClear();
        });
        clear.addEventListener('click', () => {
            field.typeahead('val', '');
            select.value = '';
            input.setCustomValidity('');
            refreshClear();
            select.dispatchEvent(new Event('change'));
        });
        filters.addEventListener('submit', event => {
            if (!input.value.trim() || select.value) return;
            const exact = options.filter(option => option.id && normalize(option.name) === normalize(input.value));
            if (exact.length === 1) {
                select.value = exact[0].id;
                return;
            }
            event.preventDefault();
            input.setCustomValidity('Selecciona una opcion de la lista.');
            input.reportValidity();
        });
        select.classList.add('d-none');
        group.classList.remove('d-none');
        filters.querySelector('label[for="' + selectId + '"]').setAttribute('for', input.id);
        refreshClear();
    }
    setupSearch('statementSupplier', 'Todos los proveedores');
    setupSearch('statementProject', 'Todos los proyectos');

    const modal = document.getElementById('statementOrderModal');
    const body = document.getElementById('statementOrderBody');
    const title = document.getElementById('statementOrderTitle');
    let controller;
    let currentUrl;

    async function loadSummary() {
        if (controller) controller.abort();
        const request = new AbortController();
        controller = request;
        body.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Cargando resumen...</div>';
        try {
            const response = await fetch(currentUrl, { signal: request.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || response.redirected) throw new Error('summary');
            const html = await response.text();
            if (controller === request && !request.signal.aborted) body.innerHTML = html;
        } catch (error) {
            if (error.name === 'AbortError' || controller !== request) return;
            body.innerHTML = '<div class="alert alert-danger">No se pudo cargar el resumen de la OC.</div><button type="button" class="btn btn-outline-primary btn-sm" id="statementRetry"><i class="ri-refresh-line me-1"></i>Reintentar</button>';
            document.getElementById('statementRetry').addEventListener('click', loadSummary);
        }
    }
    modal.addEventListener('show.bs.modal', event => {
        currentUrl = event.relatedTarget.dataset.summaryUrl;
        title.textContent = 'Orden de Compra ' + event.relatedTarget.dataset.orderFolio;
        loadSummary();
    });
    modal.addEventListener('hidden.bs.modal', () => {
        if (controller) controller.abort();
        body.replaceChildren();
    });
});
</script>
@endpush