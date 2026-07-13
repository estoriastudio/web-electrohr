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

@section('page_title', 'Subir Factura por Hito')

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
                <p class="text-muted mb-0">{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->amount, 2) }}</p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-info-subtle text-info py-1 px-2 fs-12">Proveedor: {{ $supplier->commercial_name ?? $supplier->rfc_name }}</span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h5 class="card-title mb-0"><i class="ri-upload-2-line me-1"></i> Cargar factura por hito</h5>
    </div>
    <div class="card-body">
        @if ($eligibleMilestones->isEmpty() || !$selectedMilestone)
            <div class="text-center text-muted py-4">
                <i class="ri-time-line fs-24 d-block mb-2"></i>
                No hay hitos disponibles todavía. Solo puedes subir factura cuando el hito ya aconteció.
            </div>
        @else
            <form method="POST" action="{{ route('supplier_portal.invoices.store', $purchaseOrder) }}" enctype="multipart/form-data" id="portalInvoiceForm">
                @csrf
                <input type="hidden" name="milestone_id" value="{{ $selectedMilestone->id }}">

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Hito seleccionado</label>
                        <input type="text" class="form-control bg-light"
                               value="Hito #{{ $selectedMilestone->id }} · {{ $selectedMilestone->due_date?->format('d/m/Y') }}"
                               disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Moneda</label>
                        <input type="text" class="form-control bg-light"
                               value="{{ $prefilledCurrency }}" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Importe precargado</label>
                        <input type="text" class="form-control bg-light"
                               value="{{ $prefilledCurrency }} {{ number_format((float) $prefilledAmount, 2) }}"
                               disabled>
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
                                <label class="form-label fw-medium">Factura XML</label>
                                <input type="file" name="xml_file" accept=".xml,text/xml" class="form-control @error('xml_file') is-invalid @enderror">
                                <div class="form-text">Opcional.</div>
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
    const drops = document.querySelectorAll('.drop-column');

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
