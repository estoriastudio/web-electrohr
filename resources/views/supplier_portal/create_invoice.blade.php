@extends('layouts.app')

@push('styles')
<style>
    .drop-column {
        border: 1px dashed #b9c2cd;
        border-radius: 0.75rem;
        background: #f8fafc;
        transition: all 0.2s ease;
        min-height: 210px;
    }

    .drop-column.dragover {
        border-color: #0d6efd;
        background: #eef5ff;
    }

    .drop-column .drop-icon {
        width: 44px;
        height: 44px;
        border-radius: 999px;
        background: rgba(13, 110, 253, 0.1);
        color: #0d6efd;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
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
            <form method="POST" action="{{ route('supplier_portal.invoices.store', $purchaseOrder) }}" enctype="multipart/form-data" id="portalInvoiceForm">
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Importe total de la OC</label>
                        <input type="text" class="form-control bg-light"
                               value="{{ $purchaseOrder->currency }} {{ number_format((float) $purchaseOrder->amount, 2) }}"
                               disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Importe facturado</label>
                        <input type="text" class="form-control bg-light"
                               value="{{ $purchaseOrder->currency }} {{ number_format($invoicedAmount, 2) }}" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Importe pendiente</label>
                        <input type="text" class="form-control bg-light"
                               value="{{ $purchaseOrder->currency }} {{ number_format($pendingAmount, 2) }}"
                               disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Importe de esta factura <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}" min="0.01" max="{{ number_format($pendingAmount, 2, '.', '') }}" step="0.01" required>
                        <div class="form-text">No puede exceder el importe pendiente de la OC.</div>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Folio fiscal (UUID) <span class="text-danger">*</span></label>
                        <input type="text" name="folio" class="form-control @error('folio') is-invalid @enderror"
                               value="{{ old('folio') }}" maxlength="100" placeholder="Se llena automáticamente desde el XML" required readonly>
                        <div class="form-text">Se obtiene automáticamente del XML de la factura (TimbreFiscalDigital UUID).</div>
                        @error('folio')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="drop-column p-3" data-drop-target="factura">
                            <h6 class="fw-semibold mb-2">Factura</h6>
                            <p class="text-muted fs-13 mb-3">Sube aquí el PDF y el XML de la factura.</p>

                            <div class="mb-3 text-center">
                                <span class="drop-icon"><i class="ri-file-upload-line"></i></span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-medium">Factura PDF <span class="text-danger">*</span></label>
                                <input type="file" name="pdf_file" accept=".pdf" class="form-control @error('pdf_file') is-invalid @enderror" required>
                            </div>

                            <div class="mb-0">
                                <label class="form-label fw-medium">Factura XML <span class="text-danger">*</span></label>
                                <input type="file" name="xml_file" accept=".xml,text/xml" class="form-control @error('xml_file') is-invalid @enderror" required>
                                <div class="form-text">Obligatorio. De aquí se leerá el folio fiscal (UUID).</div>
                                @error('xml_file')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="drop-column p-3" data-drop-target="evidencia">
                            <h6 class="fw-semibold mb-2">Evidencia de entrega</h6>
                            <p class="text-muted fs-13 mb-3">Adjunta evidencia en PDF o imagen.</p>

                            <div class="mb-3 text-center">
                                <span class="drop-icon"><i class="ri-archive-stack-line"></i></span>
                            </div>

                            <div class="mb-0">
                                <label class="form-label fw-medium">Archivo de evidencia <span class="text-danger">*</span></label>
                                <input type="file" name="evidence_file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control @error('evidence_file') is-invalid @enderror" required>
                                <div class="form-text">Formatos permitidos: PDF, JPG, PNG, WEBP. Máximo 10 MB.</div>
                            </div>
                        </div>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('portalInvoiceForm');
    const folioInput = form ? form.querySelector('input[name="folio"]') : null;
    const xmlInput = form ? form.querySelector('input[name="xml_file"]') : null;
    const drops = document.querySelectorAll('.drop-column');

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

    function fillFolioFromXmlFile(file) {
        if (!file || !folioInput) return;

        const fileName = String(file.name || '').toLowerCase();
        if (!fileName.endsWith('.xml')) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const xmlText = String(e.target?.result || '');
            const uuid = readFiscalFolioFromXmlText(xmlText);
            if (!uuid) {
                folioInput.value = '';
                folioInput.classList.add('is-invalid');
                return;
            }

            folioInput.classList.remove('is-invalid');
            folioInput.value = uuid;
        };
        reader.onerror = function () {
            folioInput.value = '';
            folioInput.classList.add('is-invalid');
        };
        reader.readAsText(file);
    }

    if (xmlInput) {
        xmlInput.addEventListener('change', function () {
            const file = this.files && this.files.length ? this.files[0] : null;
            fillFolioFromXmlFile(file);
        });
    }

    drops.forEach(function (drop) {
        drop.addEventListener('dragover', function (e) {
            e.preventDefault();
            drop.classList.add('dragover');
        });

        drop.addEventListener('dragleave', function () {
            drop.classList.remove('dragover');
        });

        drop.addEventListener('drop', function (e) {
            e.preventDefault();
            drop.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (!files || !files.length) return;

            const target = drop.dataset.dropTarget;
            if (target === 'factura') {
                const pdfInput = drop.querySelector('input[name="pdf_file"]');
                const xmlInput = drop.querySelector('input[name="xml_file"]');
                const file = files[0];
                const name = file.name.toLowerCase();
                const dt = new DataTransfer();
                dt.items.add(file);

                if (name.endsWith('.pdf')) {
                    pdfInput.files = dt.files;
                } else if (name.endsWith('.xml')) {
                    xmlInput.files = dt.files;
                    xmlInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            if (target === 'evidencia') {
                const evidenceInput = drop.querySelector('input[name="evidence_file"]');
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                evidenceInput.files = dt.files;
            }
        });
    });
});
</script>
@endpush
