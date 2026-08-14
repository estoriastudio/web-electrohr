@extends('layouts.app')

@section('page_title', 'Nota de Crédito')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.dashboard') }}">Portal</a></li>
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.purchase_orders.index') }}">Órdenes de Compra</a></li>
    <li class="breadcrumb-item active">Nota de Crédito</li>
@endsection

@section('content')
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
                <h5 class="mb-1">Factura {{ $invoice->folio ?: ('FACT-' . $invoice->id) }}</h5>
                <p class="text-muted mb-0">OC {{ $purchaseOrder->folio }} · {{ $purchaseOrder->project ?: 'Sin proyecto' }}</p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-info-subtle text-info py-1 px-2 fs-12">{{ $purchaseOrder->currency }} {{ number_format((float) $invoice->amount, 2) }}</span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom">
        <h5 class="card-title mb-0"><i class="ri-file-warning-line me-1"></i> {{ $invoice->credit_note_file_path ? 'Editar nota de crédito' : 'Agregar nota de crédito' }}</h5>
    </div>
    <div class="card-body">
        @if ($invoice->status === 'aceptada')
            <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
                <i class="ri-information-line fs-20"></i>
                <div>Al guardar la nota de crédito, esta factura volverá a estatus <strong>Pendiente</strong> para validación administrativa.</div>
            </div>
        @else
            <div class="alert alert-info d-flex align-items-center gap-2" role="alert">
                <i class="ri-information-line fs-20"></i>
                <div>La factura se enviará a validación administrativa al guardar la nota de crédito.</div>
            </div>
        @endif

        <form method="POST" action="{{ route('supplier_portal.invoices.credit_note.update', [$purchaseOrder, $invoice]) }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-medium">Importe de factura</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ $purchaseOrder->currency }}</span>
                        <input type="text" class="form-control bg-light" value="{{ number_format((float) $invoice->amount, 2) }}" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="credit_note_amount" class="form-label fw-medium">Importe de nota de crédito <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">{{ $purchaseOrder->currency }}</span>
                        <input type="number" name="credit_note_amount" id="credit_note_amount" class="form-control @error('credit_note_amount') is-invalid @enderror"
                               value="{{ old('credit_note_amount', $invoice->credit_note_amount) }}" min="0.01" max="{{ $invoice->amount }}" step="0.01" required>
                        @error('credit_note_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-text">Se intenta obtener automáticamente desde el XML.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Alcance líquido resultante</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ $purchaseOrder->currency }}</span>
                        <input type="text" id="net_scope" class="form-control bg-light" value="0.00" readonly>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="credit_note_pdf_file" class="form-label fw-medium">Nota de crédito PDF <span class="text-danger">*</span></label>
                    <input type="file" name="credit_note_pdf_file" id="credit_note_pdf_file" accept=".pdf" class="form-control @error('credit_note_pdf_file') is-invalid @enderror" required>
                    @if ($invoice->credit_note_file_path)
                        <div class="form-text">Archivo actual: <a href="{{ route('supplier_portal.invoices.download_file', ['purchaseOrder' => $purchaseOrder, 'invoice' => $invoice, 'type' => 'credit_note_pdf']) }}" target="_blank">ver PDF</a>.</div>
                    @endif
                    @error('credit_note_pdf_file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="credit_note_xml_file" class="form-label fw-medium">Nota de crédito XML <span class="text-danger">*</span></label>
                    <input type="file" name="credit_note_xml_file" id="credit_note_xml_file" accept=".xml,text/xml" class="form-control @error('credit_note_xml_file') is-invalid @enderror" required>
                    @if ($invoice->credit_note_xml_file_path)
                        <div class="form-text">Archivo actual: <a href="{{ route('supplier_portal.invoices.download_file', ['purchaseOrder' => $purchaseOrder, 'invoice' => $invoice, 'type' => 'credit_note_xml']) }}" target="_blank">ver XML</a>.</div>
                    @endif
                    @error('credit_note_xml_file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-4 d-flex justify-content-end gap-2">
                <a href="{{ route('supplier_portal.purchase_orders.index') }}" class="btn btn-light">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Guardar nota de crédito</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const creditNoteAmountInput = document.getElementById('credit_note_amount');
    const creditNoteXmlInput = document.getElementById('credit_note_xml_file');
    const netScopeInput = document.getElementById('net_scope');
    const invoiceAmount = {{ (float) $invoice->amount }};

    function updateNetScope() {
        const creditNoteAmount = Number.parseFloat(creditNoteAmountInput.value) || 0;
        netScopeInput.value = Math.max(0, invoiceAmount - creditNoteAmount).toFixed(2);
    }

    creditNoteAmountInput.addEventListener('input', updateNetScope);
    creditNoteXmlInput.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (event) {
            const xmlDocument = new DOMParser().parseFromString(String(event.target?.result || ''), 'application/xml');
            if (xmlDocument.querySelector('parsererror')) return;

            const comprobante = Array.from(xmlDocument.getElementsByTagName('*')).find(function (node) {
                return node.localName === 'Comprobante';
            });
            const total = Number.parseFloat(comprobante?.getAttribute('Total') || comprobante?.getAttribute('total'));
            if (!Number.isFinite(total)) return;

            creditNoteAmountInput.value = total.toFixed(2);
            updateNetScope();
        };
        reader.readAsText(file);
    });

    updateNetScope();
});
</script>
@endpush