@php
    $estimateTypeMap = [
        'estimacion' => ['label' => 'Estimación', 'class' => 'bg-primary-subtle text-primary'],
        'nota_credito' => ['label' => 'Nota de Crédito', 'class' => 'bg-danger-subtle text-danger'],
        'anticipo' => ['label' => 'Anticipo', 'class' => 'bg-info-subtle text-info'],
    ];
    $estimateStatusMap = [
        'pendiente' => ['label' => 'Pendiente', 'class' => 'bg-warning-subtle text-warning'],
        'pagada' => ['label' => 'Pagada', 'class' => 'bg-success-subtle text-success'],
    ];
    $estimateCurrency = $project->currency ?: 'MXN';
    $contractValue = (float) ($project->project_value ?? 0);
    $financialEstimates = $project->estimates->whereIn('type', ['estimacion', 'nota_credito']);
    $estimatedWorkAmount = $project->estimates->where('type', 'estimacion')->sum(fn ($estimate) => (float) $estimate->estimate_amount);
    $advanceAmount = $project->estimates->where('type', 'anticipo')->sum(fn ($estimate) => (float) $estimate->estimate_amount);
    $creditNoteAmount = $project->estimates->where('type', 'nota_credito')->sum(fn ($estimate) => (float) $estimate->estimate_amount);
    $tableEstimateTotal = $financialEstimates->sum(fn ($estimate) => (float) $estimate->estimate_amount);
    $tableLiquidTotal = $financialEstimates->sum(fn ($estimate) => $estimate->liquid_amount);
    $remainingToEstimate = $contractValue - $advanceAmount - $estimatedWorkAmount - $creditNoteAmount;
    $physicalProgress = $contractValue > 0 ? ($estimatedWorkAmount / $contractValue) * 100 : 0;
    $estimateDocuments = [
        'invoice_pdf' => ['label' => 'Factura PDF', 'path' => 'invoice_pdf_path'],
        'invoice_xml' => ['label' => 'Factura XML', 'path' => 'invoice_xml_path'],
        'credit_note_pdf' => ['label' => 'Nota de crédito PDF', 'path' => 'credit_note_pdf_path'],
        'credit_note_xml' => ['label' => 'Nota de crédito XML', 'path' => 'credit_note_xml_path'],
        'spei_receipt' => ['label' => 'Comprobante SPEI', 'path' => 'spei_receipt_path'],
    ];
    $documentErrorModalId = old('estimate_document_form') ? 'modalEstimateDocuments' . old('estimate_document_form') : null;
@endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h5 class="card-title mb-0"><i class="ri-funds-line me-1 text-muted"></i> Estimaciones</h5>
        <a href="{{ route('projects.estimates.create', $project) }}" class="btn btn-sm btn-primary">
            <i class="ri-add-line me-1"></i> Registrar estimación
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle table-hover table-centered mb-0">
                <thead class="bg-light-subtle">
                    <tr>
                        <th>EST</th>
                        <th>FACT</th>
                        <th>Archivos</th>
                        <th class="text-end">IMP. EST</th>
                        <th class="text-end">ALCANCE LIQ.</th>
                        <th>FECHA PAGO</th>
                        <th>ESTATUS</th>
                        <th>Tipo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($project->estimates as $estimate)
                        @php
                            $type = $estimateTypeMap[$estimate->type] ?? ['label' => $estimate->type, 'class' => 'bg-secondary-subtle text-secondary'];
                            $status = $estimateStatusMap[$estimate->status] ?? ['label' => $estimate->status, 'class' => 'bg-secondary-subtle text-secondary'];
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $estimate->estimate_number }}</td>
                            <td>
                                <div>{{ $estimate->invoice_number ?: '—' }}</div>
                                <small class="text-muted fs-12">{{ $estimate->invoice_date?->format('d/m/Y') ?? '—' }}</small>
                            </td>
                            <td class="text-nowrap">
                                <div class="d-flex gap-1">
                                    @foreach ($estimateDocuments as $document => $definition)
                                        @if ($estimate->{$definition['path']})
                                            <a href="{{ route('projects.estimates.documents.download', [$estimate, $document]) }}"
                                               class="rounded-circle d-inline-block bg-success"
                                               style="width: 10px; height: 10px;" target="_blank"
                                               data-bs-toggle="tooltip" title="{{ $definition['label'] }}: disponible"></a>
                                        @else
                                            <span class="rounded-circle d-inline-block bg-secondary opacity-50"
                                                  style="width: 10px; height: 10px;" data-bs-toggle="tooltip"
                                                  title="{{ $definition['label'] }}: pendiente"></span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-end fw-medium text-nowrap">{{ $estimateCurrency }} {{ number_format((float) $estimate->estimate_amount, 2) }}</td>
                            <td class="text-end fw-semibold text-success text-nowrap">{{ $estimateCurrency }} {{ number_format($estimate->liquid_amount, 2) }}</td>
                            <td class="text-muted fs-13 text-nowrap">{{ $estimate->payment_date?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="badge {{ $status['class'] }} py-1 px-2 fs-12">{{ $status['label'] }}</span></td>
                            <td><span class="badge {{ $type['class'] }} py-1 px-2 fs-12">{{ $type['label'] }}</span></td>
                            <td>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-soft-secondary btn-sm"
                                            title="Gestionar archivos" data-bs-toggle="modal"
                                            data-bs-target="#modalEstimateDocuments{{ $estimate->id }}">
                                        <i class="ri-attachment-2"></i>
                                    </button>
                                    <button type="button" class="btn btn-soft-primary btn-sm js-edit-estimate"
                                            title="Editar estimación" data-bs-toggle="modal" data-bs-target="#modalEditEstimate"
                                            data-url="{{ route('projects.estimates.update', $estimate) }}"
                                            data-number="{{ $estimate->estimate_number }}"
                                            data-date="{{ $estimate->estimate_date->format('Y-m-d') }}"
                                            data-type="{{ $estimate->type }}"
                                            data-invoice-number="{{ $estimate->invoice_number }}"
                                            data-invoice-date="{{ $estimate->invoice_date?->format('Y-m-d') }}"
                                            data-payment-date="{{ $estimate->payment_date?->format('Y-m-d') }}"
                                            data-status="{{ $estimate->status }}"
                                            data-estimate-amount="{{ $estimate->estimate_amount }}"
                                            data-returned-retention-amount="{{ $estimate->returned_retention_amount }}"
                                            data-disfp-deduction="{{ $estimate->disfp_deduction }}"
                                            data-apaee-deduction="{{ $estimate->apaee_deduction }}"
                                            data-inc-retention-amount="{{ $estimate->inc_retention_amount }}"
                                            data-vat-retention-amount="{{ $estimate->vat_retention_amount }}"
                                            data-advance-amortization-amount="{{ $estimate->advance_amortization_amount }}"
                                            data-advance-amortization-vat-amount="{{ $estimate->advance_amortization_vat_amount }}"
                                            data-funeral-expense-amount="{{ $estimate->funeral_expense_amount }}"
                                            data-delay-penalty-amount="{{ $estimate->delay_penalty_amount }}"
                                            data-notes="{{ $estimate->notes }}">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <form action="{{ route('projects.estimates.destroy', $estimate) }}" method="POST" onsubmit="return confirm('¿Eliminar esta estimación?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar estimación"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="ri-funds-line fs-24 d-block mb-1 opacity-50"></i>
                                Sin estimaciones registradas aún.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($project->estimates->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="fw-semibold">Totales: Estimaciones y Notas de Crédito</td>
                            <td class="text-end fw-semibold text-nowrap">{{ $estimateCurrency }} {{ number_format($tableEstimateTotal, 2) }}</td>
                            <td class="text-end fw-semibold text-success text-nowrap">{{ $estimateCurrency }} {{ number_format($tableLiquidTotal, 2) }}</td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
    <div class="card-footer">
        <div class="row g-3">
            <div class="col-sm-6 col-xl"><div class="text-muted fs-12">Imp. contratado</div><div class="fw-semibold">{{ $estimateCurrency }} {{ number_format($contractValue, 2) }}</div></div>
            <div class="col-sm-6 col-xl"><div class="text-muted fs-12">Imp. anticipo</div><div class="fw-semibold text-info">{{ $estimateCurrency }} {{ number_format($advanceAmount, 2) }}</div></div>
            <div class="col-sm-6 col-xl"><div class="text-muted fs-12">Imp. NC</div><div class="fw-semibold text-danger">{{ $estimateCurrency }} {{ number_format($creditNoteAmount, 2) }}</div></div>
            <div class="col-sm-6 col-xl"><div class="text-muted fs-12">Imp. estimado</div><div class="fw-semibold text-primary">{{ $estimateCurrency }} {{ number_format($estimatedWorkAmount, 2) }}</div></div>
            <div class="col-sm-6 col-xl"><div class="text-muted fs-12">Por estimar</div><div class="fw-semibold {{ $remainingToEstimate < 0 ? 'text-danger' : 'text-success' }}">{{ $estimateCurrency }} {{ number_format($remainingToEstimate, 2) }}</div></div>
            <div class="col-sm-6 col-xl text-xl-end"><div class="text-muted fs-12">Avance físico</div><div class="fw-semibold text-danger display-6">{{ number_format($physicalProgress, 2) }}%</div></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditEstimate" tabindex="-1" aria-labelledby="modalEditEstimateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <form id="formEditEstimate" action="" method="POST" class="h-100">
            <div class="modal-content">
            @csrf
            @method('PUT')
            <input type="hidden" name="estimate_form" value="edit">
            <div class="modal-header"><h5 class="modal-title" id="modalEditEstimateLabel"><i class="ri-edit-line me-1"></i> Editar estimación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">@include('estimates._form', ['prefix' => 'edit', 'estimate' => null])</div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Guardar cambios</button></div>
            </div>
        </form>
    </div>
</div>

@foreach ($project->estimates as $estimate)
    @include('estimates._documents_modal', ['estimate' => $estimate])
@endforeach

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var currency = @json($estimateCurrency);

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        if (typeof bootstrap !== 'undefined') new bootstrap.Tooltip(element);
    });

    var documentErrorModalId = @json($documentErrorModalId);
    var documentErrorModal = documentErrorModalId && document.getElementById(documentErrorModalId);
    if (documentErrorModal && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(documentErrorModal).show();
    }

    function amount(value) {
        return Number.parseFloat(String(value).replaceAll(',', '')) || 0;
    }

    function formatAmount(value) {
        return value.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setMoneyValue(input, value) {
        if (input.inputmask) {
            input.inputmask.setValue(value);
            return;
        }

        input.value = value;
    }

    function recalculateEstimate(prefix) {
        var modal = document.getElementById(prefix === 'create' ? 'modalCreateEstimate' : 'modalEditEstimate');
        var inputValue = function (field) { return amount(document.getElementById(prefix + '_' + field).value); };
        var setCalculatedValue = function (selector, value) { modal.querySelector(selector).value = formatAmount(value); };
        var estimateAmount = inputValue('estimate_amount');
        var returnedRetentionAmount = inputValue('returned_retention_amount');
        var vatAmount = estimateAmount * 0.16;
        var estimateTotal = estimateAmount + returnedRetentionAmount;
        var paymentsTotal = estimateTotal + vatAmount;
        var deductionsTotal = [
            'disfp_deduction', 'apaee_deduction', 'inc_retention_amount', 'vat_retention_amount',
            'advance_amortization_amount', 'advance_amortization_vat_amount', 'funeral_expense_amount', 'delay_penalty_amount',
        ].reduce(function (total, field) { return total + inputValue(field); }, 0);
        setCalculatedValue('.js-vat-amount', vatAmount);
        setCalculatedValue('.js-estimate-total', estimateTotal);
        setCalculatedValue('.js-payments-total', paymentsTotal);
        setCalculatedValue('.js-deductions-total', deductionsTotal);
        setCalculatedValue('.js-liquid-amount', paymentsTotal - deductionsTotal);
    }

    ['edit'].forEach(function (prefix) {
        var modal = document.getElementById('modalEditEstimate');
        modal.querySelectorAll('.js-estimate-money').forEach(function (input) {
            if (typeof Inputmask === 'undefined') return;
            new Inputmask('numeric', {
                radixPoint: '.', groupSeparator: ',', autoGroup: true, digits: 2,
                digitsOptional: true, allowMinus: false, rightAlign: false,
            }).mask(input);
        });
        modal.querySelectorAll('.js-payment-amount, .js-deduction-amount').forEach(function (input) {
            input.addEventListener('input', function () { recalculateEstimate(prefix); });
        });
        modal.querySelector('form').addEventListener('submit', function () {
            modal.querySelectorAll('.js-estimate-money').forEach(function (input) {
                if (input.inputmask) input.value = input.inputmask.unmaskedvalue();
            });
        });
        recalculateEstimate(prefix);
    });

    document.querySelectorAll('.js-edit-estimate').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = document.getElementById('formEditEstimate');
            var modal = document.getElementById('modalEditEstimate');
            form.action = this.dataset.url;
            document.getElementById('edit_estimate_number').value = this.dataset.number;
            document.getElementById('edit_estimate_date').value = this.dataset.date;
            document.getElementById('edit_type').value = this.dataset.type;
            document.getElementById('edit_invoice_number').value = this.dataset.invoiceNumber || '';
            document.getElementById('edit_invoice_date').value = this.dataset.invoiceDate || '';
            document.getElementById('edit_payment_date').value = this.dataset.paymentDate || '';
            document.getElementById('edit_status').value = this.dataset.status || 'pendiente';
            [
                ['estimate_amount', 'estimateAmount'], ['returned_retention_amount', 'returnedRetentionAmount'],
                ['disfp_deduction', 'disfpDeduction'], ['apaee_deduction', 'apaeeDeduction'],
                ['inc_retention_amount', 'incRetentionAmount'], ['vat_retention_amount', 'vatRetentionAmount'],
                ['advance_amortization_amount', 'advanceAmortizationAmount'],
                ['advance_amortization_vat_amount', 'advanceAmortizationVatAmount'],
                ['funeral_expense_amount', 'funeralExpenseAmount'], ['delay_penalty_amount', 'delayPenaltyAmount'],
            ].forEach(function (field) { setMoneyValue(document.getElementById('edit_' + field[0]), this.dataset[field[1]] || 0); }, this);
            document.getElementById('edit_notes').value = this.dataset.notes || '';
            recalculateEstimate('edit');
        });
    });

});
</script>
@endpush