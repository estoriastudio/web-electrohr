@extends('layouts.app')

@push('styles')
<style>
    .portal-dropzone {
        border: 1px dashed #c4ced9;
        border-radius: 0.75rem;
        background: #f8fafc;
        transition: all 0.2s ease;
        min-height: 150px;
    }

    .portal-dropzone:hover,
    .portal-dropzone.dz-drag-hover {
        border-color: #0d6efd;
        background: #eef5ff;
    }

    .portal-dropzone .dz-message {
        margin: 0;
        text-align: center;
        color: #64748b;
        padding: 1.25rem 1rem;
    }

    .portal-dropzone .dz-message i {
        display: block;
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: #0d6efd;
    }

    .portal-dropzone .dz-preview {
        margin: 0.75rem 0 0;
    }

    .portal-dropzone .dz-preview .dz-details {
        padding: 0.8rem;
    }

    .portal-dropzone.has-file {
        border-color: #198754;
        background: #eefaf3;
    }

    .portal-dropzone.has-file .dz-message {
        display: none;
    }

    .portal-dropzone .portal-file-preview {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        border: 1px solid #cfe9da;
        background: #ffffff;
        border-radius: 0.7rem;
        padding: 0.65rem 0.75rem;
        margin: 0.5rem 0 0;
    }

    .portal-dropzone .portal-file-preview .portal-file-icon {
        width: 34px;
        height: 34px;
        border-radius: 0.55rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #e8f2ff;
        color: #0d6efd;
        font-size: 1rem;
        flex: 0 0 auto;
    }

    .portal-dropzone .portal-file-preview .portal-file-meta {
        min-width: 0;
        flex: 1 1 auto;
    }

    .portal-dropzone .portal-file-preview .portal-file-name {
        display: block;
        font-size: 0.83rem;
        font-weight: 600;
        color: #1f2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .portal-dropzone .portal-file-preview .portal-file-size {
        display: block;
        font-size: 0.75rem;
        color: #64748b;
    }

    .portal-dropzone .portal-file-preview .dz-remove {
        color: #b42318;
        font-size: 0.78rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
    }

    .portal-dropzone .portal-file-preview .dz-remove:hover {
        text-decoration: underline;
    }

    .portal-dropzone .dz-error-message {
        margin-top: 0.5rem;
    }

    .portal-dropzone.is-invalid {
        border-color: #dc3545;
        background: #fff5f5;
    }

    .xml-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.4rem;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .xml-status-badge.is-auto {
        background: #e7f6ee;
        color: #137a4f;
    }

    .xml-status-badge.is-manual {
        background: #fff4de;
        color: #9a6700;
    }
</style>
@endpush

@section('page_title', 'Subir Factura')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.dashboard') }}">Portal</a></li>
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.purchase_orders.index') }}">Órdenes de Compra</a></li>
    <li class="breadcrumb-item active">Subir Factura</li>
@endsection

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-8">
                <h5 class="mb-1">OC {{ $purchaseOrder->folio }}</h5>
                <p class="text-muted mb-0">{{ $purchaseOrder->project ?: 'Sin proyecto' }}{{ $purchaseOrder->site ? ' · ' . $purchaseOrder->site : '' }}</p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-info-subtle text-info py-1 px-2 fs-12">Proveedor: {{ $supplier->commercial_name ?? $supplier->rfc_name }}</span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h5 class="card-title mb-0"><i class="ri-upload-2-line me-1"></i> Cargar factura de la orden de compra</h5>
    </div>
    <div class="card-body">
        @if ($pendingAmount <= 0)
            <div class="text-center text-muted py-4">
                <i class="ri-checkbox-circle-line fs-24 d-block mb-2 text-success"></i>
                Esta orden de compra ya está facturada por completo.
            </div>
        @else
            <form method="POST" action="{{ route('supplier_portal.invoices.store', $purchaseOrder) }}" enctype="multipart/form-data" id="portalInvoiceForm" novalidate>
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Importe total de la OC</label>
                        <input type="text" class="form-control bg-light"
                               value="{{ $purchaseOrder->currency }} {{ number_format((float) $purchaseOrder->total_with_iva, 2) }}"
                               disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Importe facturado</label>
                                                <label class="form-label fw-medium">Importe registrado</label>
                        <input type="text" class="form-control bg-light"
                               value="{{ $purchaseOrder->currency }} {{ number_format($invoicedAmount, 2) }}" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Importe pendiente</label>
                        <input type="text" class="form-control bg-light"
                               value="{{ $purchaseOrder->currency }} {{ number_format($pendingAmount, 2) }}"
                               disabled>
                    </div>
                </div>

                <div class="card border mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-medium">Fecha de vencimiento <span class="text-danger">*</span></label>
                                <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                                       value="{{ old('due_date') }}" required>
                                @error('due_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <h6 class="fw-semibold mb-2">Factura</h6>
                            <p class="text-muted fs-13 mb-3">Sube aquí el PDF y el XML de la factura.</p>

                            <div class="mb-3">
                                <label class="form-label fw-medium">Factura PDF <span class="text-danger">*</span></label>
                                <input type="file" name="pdf_file" accept=".pdf" class="d-none @error('pdf_file') is-invalid @enderror" required>
                                <div id="dzInvoicePdf"
                                     class="dropzone portal-dropzone @error('pdf_file') is-invalid @enderror"
                                     data-input-name="pdf_file"
                                     data-accepted-files="application/pdf,.pdf"
                                     data-drop-icon="ri-file-pdf-2-line"
                                     data-drop-title="Arrastra el PDF de la factura"
                                     data-drop-subtitle="o da clic para cargarlo"></div>
                                @error('pdf_file')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-0">
                                <label class="form-label fw-medium">Factura XML <span class="text-danger">*</span></label>
                                <input type="file" name="xml_file" accept=".xml,text/xml" class="d-none @error('xml_file') is-invalid @enderror" required>
                                <div id="dzInvoiceXml"
                                     class="dropzone portal-dropzone @error('xml_file') is-invalid @enderror"
                                     data-input-name="xml_file"
                                     data-accepted-files="text/xml,application/xml,.xml"
                                     data-drop-icon="ri-file-code-line"
                                     data-drop-title="Arrastra el XML de la factura"
                                     data-drop-subtitle="o da clic para cargarlo"></div>
                                <div class="form-text">Obligatorio. De aquí se leerá el folio fiscal (UUID).</div>
                                @error('xml_file')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mt-3">
                                <label class="form-label fw-medium">Folio fiscal (UUID) <span class="text-danger">*</span></label>
                                <input type="text" name="folio" class="form-control @error('folio') is-invalid @enderror"
                                       value="{{ old('folio') }}" maxlength="100" placeholder="Se llena automáticamente desde el XML" required readonly>
                                <div class="form-text">Se obtiene automáticamente del XML de la factura (TimbreFiscalDigital UUID).</div>
                                @error('folio')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card border h-100">
                            <div class="card-body">
                                <input type="hidden" name="has_credit_note" value="0">
                                <h6 class="fw-semibold mb-3">Incluye nota de crédito</h6>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="hasCreditNoteSwitch"
                                           name="has_credit_note" value="1" {{ old('has_credit_note') ? 'checked' : '' }}>
                                    <label class="form-check-label fw-medium" for="hasCreditNoteSwitch">
                                        Esta carga incluye nota de crédito
                                    </label>
                                </div>

                                <div id="creditNoteSection" class="{{ old('has_credit_note') ? '' : 'd-none' }}">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-medium">Nota de crédito PDF <span class="text-danger">*</span></label>
                                            <input type="file" name="credit_note_pdf_file" accept=".pdf"
                                                   class="d-none @error('credit_note_pdf_file') is-invalid @enderror">
                                            <div id="dzCreditNotePdf"
                                                 class="dropzone portal-dropzone @error('credit_note_pdf_file') is-invalid @enderror"
                                                 data-input-name="credit_note_pdf_file"
                                                 data-accepted-files="application/pdf,.pdf"
                                                 data-drop-icon="ri-file-pdf-2-line"
                                                 data-drop-title="Arrastra tu PDF aquí"
                                                 data-drop-subtitle="o da clic para buscarlo"></div>
                                            @error('credit_note_pdf_file')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-medium">Nota de crédito XML <span class="text-danger">*</span></label>
                                            <input type="file" name="credit_note_xml_file" accept=".xml,text/xml"
                                                   class="d-none @error('credit_note_xml_file') is-invalid @enderror">
                                            <div id="dzCreditNoteXml"
                                                 class="dropzone portal-dropzone @error('credit_note_xml_file') is-invalid @enderror"
                                                 data-input-name="credit_note_xml_file"
                                                 data-accepted-files="text/xml,application/xml,.xml"
                                                 data-drop-icon="ri-file-code-line"
                                                 data-drop-title="Arrastra tu XML aquí"
                                                 data-drop-subtitle="o da clic para buscarlo"></div>
                                            @error('credit_note_xml_file')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-medium">Importe nota de crédito <span class="text-danger">*</span></label>
                                            <input type="number" name="credit_note_amount" id="creditNoteAmountInput"
                                                   class="form-control @error('credit_note_amount') is-invalid @enderror"
                                                   value="{{ old('credit_note_amount') }}" min="0" step="0.01">
                                            <div class="form-text">Se intenta leer automáticamente del XML de nota de crédito.</div>
                                            <span id="creditNoteAmountStatus" class="xml-status-badge is-manual">Captura manual</span>
                                            @error('credit_note_amount')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-lg-6">
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <h6 class="fw-semibold mb-2">Evidencia de entrega</h6>
                            <p class="text-muted fs-13 mb-3">Adjunta evidencia en PDF o imagen.</p>

                            <div class="mb-0">
                                <label class="form-label fw-medium">Archivo de evidencia <span class="text-danger">*</span></label>
                                <input type="file" name="evidence_file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="d-none @error('evidence_file') is-invalid @enderror" required>
                                <div id="dzEvidence"
                                     class="dropzone portal-dropzone @error('evidence_file') is-invalid @enderror"
                                     data-input-name="evidence_file"
                                     data-accepted-files="application/pdf,image/jpeg,image/jpg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp"
                                     data-drop-icon="ri-archive-stack-line"
                                     data-drop-title="Arrastra tu evidencia aquí"
                                     data-drop-subtitle="o da clic para cargarla"></div>
                                <div class="form-text">Formatos permitidos: PDF, JPG, PNG, WEBP. Máximo 10 MB.</div>
                                @error('evidence_file')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label fw-medium">Importe de esta factura <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}" min="0.01" step="0.01" required>
                        <div class="form-text">Se intenta obtener automáticamente del XML de la factura.</div>
                        <span id="invoiceAmountStatus" class="xml-status-badge is-manual">Captura manual</span>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-lg-4">
                        <label class="form-label fw-medium">Alcance Liquido</label>
                        <div class="input-group">
                            <span class="input-group-text">{{ $purchaseOrder->currency }}</span>
                            <input type="number" id="netScopeInput" class="form-control" value="0.00" min="0" step="0.01" readonly>
                        </div>
                        <div class="form-text">Si se detecta el importe en XML se calcula automáticamente. Si no, puedes editarlo.</div>
                        <span id="netScopeStatus" class="xml-status-badge is-manual">Editable manual</span>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('supplier_portal.purchase_orders.index') }}" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Enviar factura
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

<div class="modal fade" id="invoiceValidationModal" tabindex="-1" aria-labelledby="invoiceValidationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceValidationModalLabel">
                    <i class="ri-error-warning-line text-danger me-1"></i> Revisa la información de tu factura
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Completa los siguientes campos antes de enviar la factura.</p>
                <ul id="invoiceValidationList" class="list-group mb-3"></ul>
                <div class="alert alert-info d-flex align-items-center gap-3 mb-0" role="alert">
                    <i class="ri-question-line fs-20"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">¿Necesitas ayuda para cargar tu factura?</div>
                        <a href="{{ route('supplier_portal.invoice_guide.download') }}" class="alert-link">
                            <i class="ri-download-2-line me-1"></i> Descargar instructivo PDF
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Revisar campos</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
if (typeof window !== 'undefined' && window.Dropzone) {
    window.Dropzone.autoDiscover = false;
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('portalInvoiceForm');
    const folioInput = form ? form.querySelector('input[name="folio"]') : null;
    const xmlInput = form ? form.querySelector('input[name="xml_file"]') : null;
    const amountInput = form ? form.querySelector('input[name="amount"]') : null;
    const hasCreditNoteSwitch = form ? form.querySelector('input[name="has_credit_note"][type="checkbox"]') : null;
    const creditNoteSection = document.getElementById('creditNoteSection');
    const creditNoteAmountInput = document.getElementById('creditNoteAmountInput');
    const creditNotePdfInput = form ? form.querySelector('input[name="credit_note_pdf_file"]') : null;
    const creditNoteXmlInput = form ? form.querySelector('input[name="credit_note_xml_file"]') : null;
    const netScopeInput = document.getElementById('netScopeInput');
    const invoiceAmountStatus = document.getElementById('invoiceAmountStatus');
    const creditNoteAmountStatus = document.getElementById('creditNoteAmountStatus');
    const netScopeStatus = document.getElementById('netScopeStatus');
    const invoiceValidationModalElement = document.getElementById('invoiceValidationModal');
    const invoiceValidationList = document.getElementById('invoiceValidationList');
    const dropzonesByInput = {};
    const fieldLabels = {
        due_date: 'Fecha de vencimiento',
        pdf_file: 'Factura PDF',
        xml_file: 'Factura XML',
        folio: 'Folio fiscal (UUID)',
        amount: 'Importe de esta factura',
        evidence_file: 'Archivo de evidencia',
        credit_note_pdf_file: 'Nota de crédito PDF',
        credit_note_xml_file: 'Nota de crédito XML',
        credit_note_amount: 'Importe de nota de crédito'
    };
    let invoiceAmountLoadedFromXml = false;
    let creditNoteAmountLoadedFromXml = false;

    function getDropzoneForInput(input) {
        if (!input || !input.name || !form) return null;
        return form.querySelector(`.portal-dropzone[data-input-name="${input.name}"]`);
    }

    function clearFieldInvalidState(input) {
        if (!input) return;
        input.classList.remove('is-invalid');
        const dropzoneElement = getDropzoneForInput(input);
        if (dropzoneElement) {
            dropzoneElement.classList.remove('is-invalid');
        }
    }

    function getValidationMessage(input) {
        const label = fieldLabels[input.name] || 'Este campo';

        if (input.validity.valueMissing) {
            return `${label} es obligatorio.`;
        }
        if (input.validity.rangeUnderflow) {
            return `${label} debe ser mayor a ${input.min || 0}.`;
        }
        if (input.validity.typeMismatch || input.validity.badInput) {
            return `${label} tiene un formato inválido.`;
        }

        return `${label} necesita corrección.`;
    }

    function focusField(input) {
        const dropzoneElement = getDropzoneForInput(input);
        const target = dropzoneElement || input;

        if (!target) return;

        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (dropzoneElement) {
            dropzoneElement.setAttribute('tabindex', '-1');
        }
        target.focus({ preventScroll: true });
    }

    function showValidationModal(invalidInputs) {
        if (!invoiceValidationModalElement || !invoiceValidationList || typeof bootstrap === 'undefined') return;

        invoiceValidationList.replaceChildren();
        invalidInputs.forEach(function (input) {
            input.classList.add('is-invalid');
            const dropzoneElement = getDropzoneForInput(input);
            if (dropzoneElement) {
                dropzoneElement.classList.add('is-invalid');
            }

            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2';
            button.innerHTML = `<i class="ri-arrow-right-s-line text-primary"></i><span>${getValidationMessage(input)}</span>`;
            button.addEventListener('click', function () {
                bootstrap.Modal.getOrCreateInstance(invoiceValidationModalElement).hide();
                window.setTimeout(function () {
                    focusField(input);
                }, 200);
            });
            item.appendChild(button);
            invoiceValidationList.appendChild(item);
        });

        const validationModal = bootstrap.Modal.getOrCreateInstance(invoiceValidationModalElement);
        invoiceValidationModalElement.addEventListener('shown.bs.modal', function onShown() {
            const firstItem = invoiceValidationList.querySelector('button');
            if (firstItem) firstItem.focus();
            invoiceValidationModalElement.removeEventListener('shown.bs.modal', onShown);
        });
        validationModal.show();
    }

    function parseAmount(value) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function updateNetScope(force) {
        if (!netScopeInput || !amountInput) return;

        const amount = parseAmount(amountInput.value);
        const creditNoteAmount = (hasCreditNoteSwitch && hasCreditNoteSwitch.checked && creditNoteAmountInput)
            ? parseAmount(creditNoteAmountInput.value)
            : 0;

        const netScope = amount - creditNoteAmount;
        if (netScopeInput.readOnly || force === true || !netScopeInput.value) {
            netScopeInput.value = (netScope > 0 ? netScope : 0).toFixed(2);
        }
    }

    function setAmountReadonly(locked) {
        if (!amountInput) return;
        amountInput.readOnly = locked;
        amountInput.classList.toggle('bg-light', locked);
    }

    function setStatusBadge(el, text, isAuto) {
        if (!el) return;
        el.textContent = text;
        el.classList.toggle('is-auto', isAuto);
        el.classList.toggle('is-manual', !isAuto);
    }

    function setNetScopeReadonly(locked) {
        if (!netScopeInput) return;
        netScopeInput.readOnly = locked;
        netScopeInput.classList.toggle('bg-light', locked);

        if (locked) {
            setStatusBadge(netScopeStatus, 'Calculado automáticamente', true);
        } else {
            setStatusBadge(netScopeStatus, 'Editable manual', false);
        }
    }

    function setCreditNoteAmountReadonly(locked) {
        if (!creditNoteAmountInput) return;
        creditNoteAmountInput.readOnly = locked;
        creditNoteAmountInput.classList.toggle('bg-light', locked);
    }

    function parseMoneyValue(rawValue) {
        const normalized = String(rawValue || '').trim().replace(/,/g, '');
        const parsed = parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function normalizeInvoiceAmount(amount) {
        const cents = Math.round(amount * 100);
        const centsRemainder = Math.abs(cents % 100);

        return centsRemainder >= 1 && centsRemainder <= 9
            ? Math.trunc(cents / 100)
            : cents / 100;
    }

    function readCfdiTotalFromXmlText(xmlText) {
        try {
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(xmlText, 'application/xml');
            if (xmlDoc.querySelector('parsererror')) return null;

            const root = xmlDoc.documentElement;
            const comprobanteNode = root && root.localName === 'Comprobante'
                ? root
                : Array.from(xmlDoc.getElementsByTagName('*')).find(function (node) {
                    return node.localName === 'Comprobante';
                });

            if (!comprobanteNode) return null;

            const totalAttr = comprobanteNode.getAttribute('Total') || comprobanteNode.getAttribute('total');
            return parseMoneyValue(totalAttr);
        } catch (err) {
            return null;
        }
    }

    function setInvoiceAmountFromXml(amount) {
        if (!amountInput || !netScopeInput) return;

        if (amount === null) {
            if (invoiceAmountLoadedFromXml) {
                amountInput.value = '';
                netScopeInput.value = '0.00';
            }
            invoiceAmountLoadedFromXml = false;
            setAmountReadonly(false);
            setNetScopeReadonly(false);
            setStatusBadge(invoiceAmountStatus, 'Captura manual', false);
            return;
        }

        const normalizedAmount = normalizeInvoiceAmount(amount);
        invoiceAmountLoadedFromXml = true;
        amountInput.value = normalizedAmount.toFixed(2);
        setAmountReadonly(true);
        setNetScopeReadonly(true);
        setStatusBadge(invoiceAmountStatus, 'Detectado desde XML', true);
        updateNetScope(true);
    }

    function setCreditNoteAmountFromXml(amount) {
        if (!creditNoteAmountInput) return;

        if (amount === null) {
            if (creditNoteAmountLoadedFromXml) {
                creditNoteAmountInput.value = '';
            }
            creditNoteAmountLoadedFromXml = false;
            setCreditNoteAmountReadonly(false);
            setStatusBadge(creditNoteAmountStatus, 'Captura manual', false);
            return;
        }

        creditNoteAmountLoadedFromXml = true;
        creditNoteAmountInput.value = amount.toFixed(2);
        setCreditNoteAmountReadonly(true);
        setStatusBadge(creditNoteAmountStatus, 'Detectado desde XML', true);
        updateNetScope(true);
    }

    function toggleCreditNoteSection(resetFields) {
        if (!hasCreditNoteSwitch || !creditNoteSection) return;

        const enabled = hasCreditNoteSwitch.checked;
        creditNoteSection.classList.toggle('d-none', !enabled);

        if (creditNoteAmountInput) {
            creditNoteAmountInput.required = enabled;
        }
        if (creditNotePdfInput) {
            creditNotePdfInput.required = enabled;
        }
        if (creditNoteXmlInput) {
            creditNoteXmlInput.required = enabled;
        }

        if (!enabled && resetFields) {
            if (creditNoteAmountInput) creditNoteAmountInput.value = '';
            creditNoteAmountLoadedFromXml = false;
            setCreditNoteAmountReadonly(false);
            if (creditNotePdfInput) {
                creditNotePdfInput.value = '';
                clearDropzoneByInputName('credit_note_pdf_file');
            }
            if (creditNoteXmlInput) {
                creditNoteXmlInput.value = '';
                clearDropzoneByInputName('credit_note_xml_file');
            }
        }

        if (!enabled) {
            updateNetScope(true);
        } else {
            updateNetScope();
        }
    }

    function readFiscalFolioFromXmlText(xmlText) {
        try {
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(xmlText, 'application/xml');
            if (xmlDoc.querySelector('parsererror')) return null;

            const nodes = xmlDoc.getElementsByTagName('*');
            for (let i = 0; i < nodes.length; i++) {
                const node = nodes[i];
                if (node.localName !== 'TimbreFiscalDigital') continue;

                const uuid = (node.getAttribute('UUID') || node.getAttribute('Uuid') || node.getAttribute('uuid') || '').trim();
                if (!uuid) return null;

                const normalized = uuid.toUpperCase();
                const uuidRegex = /^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/;
                return uuidRegex.test(normalized) ? normalized : null;
            }
        } catch (err) {
            return null;
        }

        return null;
    }

    function fillInvoiceDataFromXmlFile(file) {
        if (!file) return;

        const fileName = String(file.name || '').toLowerCase();
        if (!fileName.endsWith('.xml')) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const xmlText = String(e.target?.result || '');
            const uuid = readFiscalFolioFromXmlText(xmlText);
            const total = readCfdiTotalFromXmlText(xmlText);

            if (folioInput) {
                if (!uuid) {
                    folioInput.value = '';
                    folioInput.classList.add('is-invalid');
                } else {
                    folioInput.classList.remove('is-invalid');
                    folioInput.value = uuid;
                }
            }

            setInvoiceAmountFromXml(total);
        };

        reader.onerror = function () {
            if (folioInput) {
                folioInput.value = '';
                folioInput.classList.add('is-invalid');
            }
            setInvoiceAmountFromXml(null);
        };

        reader.readAsText(file);
    }

    function fillCreditNoteAmountFromXmlFile(file) {
        if (!file || !hasCreditNoteSwitch || !hasCreditNoteSwitch.checked) return;

        const fileName = String(file.name || '').toLowerCase();
        if (!fileName.endsWith('.xml')) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const xmlText = String(e.target?.result || '');
            const total = readCfdiTotalFromXmlText(xmlText);
            setCreditNoteAmountFromXml(total);
        };

        reader.onerror = function () {
            setCreditNoteAmountFromXml(null);
        };

        reader.readAsText(file);
    }

    function setInputFile(input, file) {
        if (!input) return;
        const dt = new DataTransfer();
        if (file) {
            dt.items.add(file);
        }
        input.files = dt.files;
    }

    function clearDropzoneByInputName(inputName) {
        const dz = dropzonesByInput[inputName];
        if (dz) {
            dz.removeAllFiles(true);
        }
    }

    function showNativeFileInputByName(inputName) {
        if (!form) return;

        const input = form.querySelector(`input[name="${inputName}"]`);
        if (input) {
            input.classList.remove('d-none');
            input.classList.add('form-control');
        }

        const dzElement = form.querySelector(`.portal-dropzone[data-input-name="${inputName}"]`);
        if (dzElement) {
            dzElement.classList.add('d-none');
        }
    }

    function createDropzone(dropzoneId, options) {
        const dropzoneElement = document.getElementById(dropzoneId);
        if (!dropzoneElement || !form || typeof Dropzone === 'undefined') return null;

        const inputName = dropzoneElement.dataset.inputName;
        const input = form.querySelector(`input[name="${inputName}"]`);
        if (!input) return null;

        if (dropzonesByInput[inputName]) {
            return dropzonesByInput[inputName];
        }

        if (dropzoneElement.dropzone) {
            try {
                dropzoneElement.dropzone.destroy();
            } catch (err) {
                return null;
            }
        }

        const acceptedFiles = dropzoneElement.dataset.acceptedFiles || '';
        const icon = dropzoneElement.dataset.dropIcon || 'ri-upload-cloud-2-line';
        const title = dropzoneElement.dataset.dropTitle || 'Arrastra archivos aquí';
        const subtitle = dropzoneElement.dataset.dropSubtitle || 'o da clic para buscar';
        const previewTemplate = `
            <div class="dz-preview dz-file-preview portal-file-preview">
                <span class="portal-file-icon"><i class="ri-file-3-line"></i></span>
                <div class="portal-file-meta">
                    <span class="portal-file-name" data-dz-name></span>
                    <span class="portal-file-size" data-dz-size></span>
                </div>
                <a href="javascript:void(0);" class="dz-remove" data-dz-remove>Quitar</a>
                <strong class="dz-error-message"><span data-dz-errormessage></span></strong>
            </div>
        `;

        let dz;
        try {
            dz = new Dropzone(dropzoneElement, {
                url: '/',
                autoProcessQueue: false,
                uploadMultiple: false,
                maxFiles: 1,
                addRemoveLinks: false,
                acceptedFiles: acceptedFiles,
                clickable: true,
                previewsContainer: dropzoneElement,
                previewTemplate: previewTemplate,
                dictDefaultMessage: `<i class="${icon}"></i><h6 class="mb-1">${title}</h6><span class="fs-13">${subtitle}</span>`,
                dictRemoveFile: 'Quitar',
                dictInvalidFileType: 'Tipo de archivo no permitido.',
                dictFileTooBig: 'El archivo es demasiado grande (@{{filesize}}MB). Máximo: @{{maxFilesize}}MB.',
            });
        } catch (err) {
            return null;
        }

        dz.on('addedfile', function (file) {
            if (dz.files.length > 1) {
                dz.removeFile(dz.files[0]);
            }

            setInputFile(input, file);
            input.classList.remove('is-invalid');
            dropzoneElement.classList.remove('is-invalid');
            dropzoneElement.classList.add('has-file');

            if (typeof options.onFileSelected === 'function') {
                options.onFileSelected(file, input);
            }
        });

        dz.on('removedfile', function () {
            setInputFile(input, null);
            if (!dz.files.length) {
                dropzoneElement.classList.remove('has-file');
            }
            if (typeof options.onFileRemoved === 'function') {
                options.onFileRemoved(input);
            }
        });

        dropzoneElement.dataset.portalDzInitialized = '1';
        dropzonesByInput[inputName] = dz;
        return dz;
    }

    function showNativeFileInputs() {
        if (!form) return;

        const nativeInputs = form.querySelectorAll('input[type="file"]');
        nativeInputs.forEach(function (input) {
            input.classList.remove('d-none');
            input.classList.add('form-control');
        });

        const dzElements = form.querySelectorAll('.portal-dropzone');
        dzElements.forEach(function (el) {
            el.classList.add('d-none');
        });
    }

    function initDropzones() {
        if (typeof Dropzone === 'undefined') {
            return false;
        }

        const invoicePdfDz = createDropzone('dzInvoicePdf', {});
        const invoiceXmlDz = createDropzone('dzInvoiceXml', {
            onFileSelected: function (file) {
                fillInvoiceDataFromXmlFile(file);
            },
            onFileRemoved: function () {
                if (!folioInput) return;
                folioInput.value = '';
                folioInput.classList.remove('is-invalid');
                setInvoiceAmountFromXml(null);
            }
        });
        const evidenceDz = createDropzone('dzEvidence', {});
        const creditPdfDz = createDropzone('dzCreditNotePdf', {});
        const creditXmlDz = createDropzone('dzCreditNoteXml', {
            onFileSelected: function (file) {
                fillCreditNoteAmountFromXmlFile(file);
            },
            onFileRemoved: function () {
                if (creditNoteAmountLoadedFromXml && creditNoteAmountInput) {
                    creditNoteAmountInput.value = '';
                }
                creditNoteAmountLoadedFromXml = false;
                setCreditNoteAmountReadonly(false);
                updateNetScope(true);
            }
        });

        const allReady = [invoicePdfDz, invoiceXmlDz, evidenceDz, creditPdfDz, creditXmlDz].every(function (dz) {
            return dz !== null;
        });

        return allReady;
    }

    function bootstrapDropzones() {
        const maxRetries = 15;
        let retries = 0;

        function attempt() {
            const ready = initDropzones();
            if (ready) return;

            retries += 1;
            if (retries >= maxRetries) {
                ['pdf_file', 'xml_file', 'evidence_file', 'credit_note_pdf_file', 'credit_note_xml_file'].forEach(function (inputName) {
                    if (!dropzonesByInput[inputName]) {
                        showNativeFileInputByName(inputName);
                    }
                });
                return;
            }

            window.setTimeout(attempt, 120);
        }

        attempt();
    }

    if (xmlInput) {
        xmlInput.addEventListener('change', function () {
            const file = this.files && this.files.length ? this.files[0] : null;
            fillInvoiceDataFromXmlFile(file);
        });
    }

    if (creditNoteXmlInput) {
        creditNoteXmlInput.addEventListener('change', function () {
            const file = this.files && this.files.length ? this.files[0] : null;
            fillCreditNoteAmountFromXmlFile(file);
        });
    }

    if (amountInput) {
        amountInput.addEventListener('input', function () {
            if (!invoiceAmountLoadedFromXml) {
                setAmountReadonly(false);
                setStatusBadge(invoiceAmountStatus, 'Captura manual', false);
            }
            updateNetScope();
        });
    }

    if (creditNoteAmountInput) {
        creditNoteAmountInput.addEventListener('input', function () {
            if (!creditNoteAmountLoadedFromXml) {
                setCreditNoteAmountReadonly(false);
                setStatusBadge(creditNoteAmountStatus, 'Captura manual', false);
            }
            updateNetScope();
        });
    }

    if (netScopeInput) {
        netScopeInput.addEventListener('input', function () {
            if (!invoiceAmountLoadedFromXml) {
                setNetScopeReadonly(false);
            }
        });
    }

    if (hasCreditNoteSwitch) {
        hasCreditNoteSwitch.addEventListener('change', function () {
            toggleCreditNoteSection(true);
        });
    }

    if (form) {
        form.addEventListener('input', function (event) {
            if (event.target.matches('input, select, textarea') && event.target.validity.valid) {
                clearFieldInvalidState(event.target);
            }
        });

        form.addEventListener('change', function (event) {
            if (event.target.matches('input, select, textarea') && event.target.validity.valid) {
                clearFieldInvalidState(event.target);
            }
        });

        form.addEventListener('submit', function (event) {
            if (form.checkValidity()) return;

            event.preventDefault();
            const invalidInputs = Array.from(form.querySelectorAll('input:invalid, select:invalid, textarea:invalid'));

            if (typeof bootstrap === 'undefined') {
                form.reportValidity();
                return;
            }

            showValidationModal(invalidInputs);
        });
    }

    bootstrapDropzones();
    toggleCreditNoteSection(false);
    setAmountReadonly(false);
    setCreditNoteAmountReadonly(false);
    setNetScopeReadonly(false);
    setStatusBadge(invoiceAmountStatus, 'Captura manual', false);
    setStatusBadge(creditNoteAmountStatus, 'Captura manual', false);
    updateNetScope(true);
});
</script>
@endpush
