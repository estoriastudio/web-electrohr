@php
    $documents = [
        'invoice_pdf' => ['label' => 'Factura PDF', 'path' => 'invoice_pdf_path', 'accept' => '.pdf,application/pdf'],
        'invoice_xml' => ['label' => 'Factura XML', 'path' => 'invoice_xml_path', 'accept' => '.xml,text/xml,application/xml'],
        'credit_note_pdf' => ['label' => 'Nota de crédito PDF', 'path' => 'credit_note_pdf_path', 'accept' => '.pdf,application/pdf'],
        'credit_note_xml' => ['label' => 'Nota de crédito XML', 'path' => 'credit_note_xml_path', 'accept' => '.xml,text/xml,application/xml'],
        'spei_receipt' => ['label' => 'Comprobante SPEI', 'path' => 'spei_receipt_path', 'accept' => '.pdf,application/pdf'],
    ];
@endphp

<div class="modal fade" id="modalEstimateDocuments{{ $estimate->id }}" tabindex="-1"
     aria-labelledby="modalEstimateDocumentsLabel{{ $estimate->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('projects.estimates.documents.update', $estimate) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="estimate_document_form" value="{{ $estimate->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEstimateDocumentsLabel{{ $estimate->id }}">
                        <i class="ri-attachment-2 me-1"></i> Archivos de estimación {{ $estimate->estimate_number }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13">Los archivos son opcionales. Selecciona uno para cargarlo o reemplazarlo.</p>
                    @error('attachments', 'estimateDocuments')
                        <div class="alert alert-danger py-2">{{ $message }}</div>
                    @enderror
                    <div class="row g-3">
                        @foreach ($documents as $field => $document)
                            <div class="col-md-6">
                                <label for="estimate_{{ $estimate->id }}_{{ $field }}" class="form-label fw-medium">{{ $document['label'] }}</label>
                                <input type="file" class="form-control @error($field, 'estimateDocuments') is-invalid @enderror"
                                       id="estimate_{{ $estimate->id }}_{{ $field }}" name="{{ $field }}" accept="{{ $document['accept'] }}">
                                @if ($estimate->{$document['path']})
                                    <div class="form-text">
                                        Archivo actual:
                                        <a href="{{ route('projects.estimates.documents.download', [$estimate, $field]) }}" target="_blank">ver archivo</a>.
                                    </div>
                                @endif
                                @error($field, 'estimateDocuments')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text mt-3">PDF y XML de hasta 10 MB por archivo.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-upload-2-line me-1"></i> Guardar archivos</button>
                </div>
            </form>
        </div>
    </div>
</div>