@php
    $fileTypes = \App\Models\ProjectEstimateFile::TYPES;
    $acceptByType = collect($fileTypes)->map(fn ($type) => collect($type['extensions'])->map(fn ($ext) => '.' . $ext)->implode(','))->all();
    $hasFormErrors = (int) old('estimate_file_form') === $estimate->id;
    $hasInvoiceErrors = (int) old('estimate_invoice_form') === $estimate->id;
    $preselectedType = $hasInvoiceErrors ? 'invoice' : ($hasFormErrors ? old('file_type') : '');
@endphp

<div class="modal fade js-estimate-files-modal" id="modalEstimateDocuments{{ $estimate->id }}" tabindex="-1"
     aria-labelledby="modalEstimateDocumentsLabel{{ $estimate->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEstimateDocumentsLabel{{ $estimate->id }}">
                    <i class="ri-attachment-2 me-1"></i> Archivos de estimación {{ $estimate->estimate_number }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="border rounded p-3">
                    <label for="estimate_{{ $estimate->id }}_file_type" class="form-label fw-medium">Tipo</label>
                    <select class="form-select mb-3 js-estimate-file-type" id="estimate_{{ $estimate->id }}_file_type"
                            data-accept='@json($acceptByType)'>
                        <option value="">Selecciona un tipo…</option>
                        <option value="invoice" @selected($preselectedType === 'invoice')>Factura (PDF + XML)</option>
                        @foreach ($fileTypes as $key => $type)
                            <option value="{{ $key }}" @selected($preselectedType === $key)>{{ $type['label'] }}</option>
                        @endforeach
                    </select>

                    {{-- Factura: PDF + XML con folio fiscal y fechas --}}
                    <form action="{{ route('projects.estimates.invoices.store', $estimate) }}" method="POST" enctype="multipart/form-data"
                          class="js-estimate-invoice-form" hidden>
                        @csrf
                        <input type="hidden" name="estimate_invoice_form" value="{{ $estimate->id }}">
                        @if ($hasInvoiceErrors && $errors->estimateInvoices->any())
                            <div class="alert alert-danger py-2">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->estimateInvoices->all() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="js-invoice-blocks"></div>
                        <button type="button" class="btn btn-soft-secondary btn-sm js-add-invoice">
                            <i class="ri-add-line me-1"></i> Agregar otra factura
                        </button>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="form-text mb-0">El folio fiscal (UUID) y la fecha se leen del XML. PDF y XML de hasta 10 MB.</div>
                            <button type="submit" class="btn btn-primary js-estimate-invoice-submit" disabled>
                                <i class="ri-upload-2-line me-1"></i> Guardar facturas
                            </button>
                        </div>
                        <template class="js-invoice-template">
                            <div class="border rounded p-3 mb-3 position-relative js-invoice-block">
                                <button type="button" class="btn-close position-absolute top-0 end-0 m-2 js-remove-invoice" aria-label="Quitar factura"></button>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium">Factura PDF</label>
                                        <input type="file" class="form-control js-invoice-pdf" name="invoices[__INDEX__][pdf_file]" accept=".pdf" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium">Factura XML <small class="text-muted fw-normal">(CFDI timbrado)</small></label>
                                        <input type="file" class="form-control js-invoice-xml" name="invoices[__INDEX__][xml_file]" accept=".xml,text/xml" required>
                                        <div class="invalid-feedback">No fue posible leer el folio fiscal (UUID) del XML.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-medium">Folio fiscal (UUID)</label>
                                        <input type="text" class="form-control bg-light js-invoice-folio" name="invoices[__INDEX__][folio]" maxlength="100" readonly
                                               placeholder="Se obtiene del XML">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-medium">Fecha</label>
                                        <input type="date" class="form-control js-invoice-date" name="invoices[__INDEX__][invoice_date]" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-medium">Fecha de pago</label>
                                        <input type="date" class="form-control js-invoice-payment-date" name="invoices[__INDEX__][payment_date]" required>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </form>

                    {{-- Resto de tipos: selección múltiple de archivos --}}
                    <form action="{{ route('projects.estimates.files.store', $estimate) }}" method="POST" enctype="multipart/form-data"
                          class="js-estimate-files-form" hidden>
                        @csrf
                        <input type="hidden" name="estimate_file_form" value="{{ $estimate->id }}">
                        <input type="hidden" name="file_type" class="js-estimate-file-type-value" value="{{ $hasFormErrors ? old('file_type') : '' }}">
                        @if ($hasFormErrors && $errors->estimateFiles->any())
                            <div class="alert alert-danger py-2">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->estimateFiles->all() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <label for="estimate_{{ $estimate->id }}_files" class="form-label fw-medium">Archivos</label>
                        <input type="file" class="form-control js-estimate-files-input" id="estimate_{{ $estimate->id }}_files"
                               name="files[]" multiple required>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="form-text mb-0">PDF, XML, JPG y PNG de hasta 10 MB por archivo. Puedes seleccionar varios.</div>
                            <button type="submit" class="btn btn-primary js-estimate-files-submit" disabled>
                                <i class="ri-upload-2-line me-1"></i> Guardar archivos
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function readCfdi(xmlText) {
            try {
                var xmlDoc = new DOMParser().parseFromString(xmlText, 'application/xml');
                if (xmlDoc.querySelector('parsererror')) return null;

                var result = { uuid: null, date: '' };
                var nodes = xmlDoc.getElementsByTagName('*');
                for (var i = 0; i < nodes.length; i++) {
                    var node = nodes[i];
                    if (node.localName === 'TimbreFiscalDigital') {
                        var uuid = (node.getAttribute('UUID') || node.getAttribute('Uuid') || node.getAttribute('uuid') || '').trim().toUpperCase();
                        if (/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/.test(uuid)) result.uuid = uuid;
                    } else if (node.localName === 'Comprobante') {
                        var fecha = (node.getAttribute('Fecha') || node.getAttribute('fecha') || '').trim();
                        if (/^\d{4}-\d{2}-\d{2}/.test(fecha)) result.date = fecha.substring(0, 10);
                    }
                }
                return result.uuid ? result : null;
            } catch (err) {
                return null;
            }
        }

        document.querySelectorAll('.js-estimate-files-modal').forEach(function (modal) {
            var select = modal.querySelector('.js-estimate-file-type');
            var accepts = JSON.parse(select.dataset.accept);

            var filesForm = modal.querySelector('.js-estimate-files-form');
            var typeValue = filesForm.querySelector('.js-estimate-file-type-value');
            var filesInput = filesForm.querySelector('.js-estimate-files-input');
            var filesSubmit = filesForm.querySelector('.js-estimate-files-submit');

            var invoiceForm = modal.querySelector('.js-estimate-invoice-form');
            var blocksWrap = invoiceForm.querySelector('.js-invoice-blocks');
            var template = invoiceForm.querySelector('.js-invoice-template');
            var invoiceSubmit = invoiceForm.querySelector('.js-estimate-invoice-submit');
            var nextIndex = 0;

            function syncFiles() {
                filesInput.accept = accepts[typeValue.value] || '';
                filesSubmit.disabled = !typeValue.value || filesInput.files.length === 0;
            }

            function syncInvoices() {
                var blocks = blocksWrap.querySelectorAll('.js-invoice-block');
                var complete = blocks.length > 0 && Array.prototype.every.call(blocks, function (block) {
                    return block.querySelector('.js-invoice-pdf').files.length
                        && block.querySelector('.js-invoice-xml').files.length
                        && !block.querySelector('.js-invoice-xml').classList.contains('is-invalid')
                        && block.querySelector('.js-invoice-folio').value
                        && block.querySelector('.js-invoice-date').value
                        && block.querySelector('.js-invoice-payment-date').value;
                });
                invoiceSubmit.disabled = !complete;
            }

            function addInvoiceBlock() {
                var block = template.content.firstElementChild.cloneNode(true);
                block.innerHTML = block.innerHTML.replaceAll('__INDEX__', nextIndex++);
                blocksWrap.appendChild(block);

                var xmlInput = block.querySelector('.js-invoice-xml');
                xmlInput.addEventListener('change', function () {
                    var folio = block.querySelector('.js-invoice-folio');
                    var date = block.querySelector('.js-invoice-date');
                    var file = xmlInput.files[0];
                    xmlInput.classList.remove('is-invalid');
                    folio.value = '';
                    if (!file) { syncInvoices(); return; }

                    var reader = new FileReader();
                    reader.onload = function (event) {
                        var data = readCfdi(String(event.target.result || ''));
                        if (!data) {
                            xmlInput.classList.add('is-invalid');
                        } else {
                            folio.value = data.uuid;
                            if (data.date) date.value = data.date;
                        }
                        syncInvoices();
                    };
                    reader.onerror = function () { xmlInput.classList.add('is-invalid'); syncInvoices(); };
                    reader.readAsText(file);
                });

                block.querySelectorAll('input').forEach(function (input) {
                    input.addEventListener('input', syncInvoices);
                    input.addEventListener('change', syncInvoices);
                });
                block.querySelector('.js-remove-invoice').addEventListener('click', function () {
                    if (blocksWrap.querySelectorAll('.js-invoice-block').length > 1) {
                        block.remove();
                        syncInvoices();
                    }
                });
                syncInvoices();
            }

            function showSelectedType() {
                var isInvoice = select.value === 'invoice';
                invoiceForm.hidden = !isInvoice;
                filesForm.hidden = isInvoice || !select.value;
                typeValue.value = isInvoice ? '' : select.value;
                if (isInvoice && !blocksWrap.children.length) addInvoiceBlock();
                syncFiles();
            }

            select.addEventListener('change', showSelectedType);
            filesInput.addEventListener('change', syncFiles);
            invoiceForm.querySelector('.js-add-invoice').addEventListener('click', addInvoiceBlock);
            filesForm.addEventListener('submit', function () { filesSubmit.disabled = true; });
            invoiceForm.addEventListener('submit', function () { invoiceSubmit.disabled = true; });
            showSelectedType();
        });
    });
    </script>
    @endpush
@endonce