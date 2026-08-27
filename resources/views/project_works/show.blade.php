@extends('layouts.app')

@section('page_title', $projectWork->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyectos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.show', $projectWork->project) }}">{{ $projectWork->project->name }}</a></li>
    <li class="breadcrumb-item active">{{ $projectWork->name }}</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     ENCABEZADO
══════════════════════════════════════════════════════════════════ --}}
@php
    $wsMap = [
        'active'   => ['label' => 'Activa',   'class' => 'bg-success-subtle text-success'],
        'inactive' => ['label' => 'Inactiva', 'class' => 'bg-warning-subtle text-warning'],
    ];
    $ws = $wsMap[$projectWork->status] ?? ['label' => $projectWork->status, 'class' => 'bg-secondary-subtle text-secondary'];
@endphp

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h5 class="fw-semibold mb-0">{{ $projectWork->name }}</h5>
            <span class="badge {{ $ws['class'] }} py-1 px-2 fs-12">{{ $ws['label'] }}</span>
        </div>
        <div class="text-muted fs-13">
            <i class="ri-folder-3-line me-1"></i>
            <a href="{{ route('projects.show', $projectWork->project) }}" class="text-muted text-decoration-none">
                {{ $projectWork->project->name }}
            </a>
            &nbsp;·&nbsp;
            <i class="ri-user-line me-1"></i>{{ $projectWork->project->client_name }}
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     DATOS DEL CONTRATO
══════════════════════════════════════════════════════════════════ --}}
@if ($projectWork->supervisor || $projectWork->resident || $projectWork->contract_number ||
     $projectWork->contract_start_date || $projectWork->contract_end_date ||
     $projectWork->contract_value || $projectWork->currency)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h5 class="card-title mb-0">
            <i class="ri-file-text-line me-1 text-muted"></i> Datos del Contrato
        </h5>
        <a href="{{ route('project_works.edit', $projectWork) }}" class="btn btn-sm btn-soft-primary">
            <i class="ri-edit-line me-1"></i> Editar
        </a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @if ($projectWork->supervisor)
            <div class="col-md-6 col-xl-3">
                <div class="text-muted fs-12 mb-1">Supervisor</div>
                <div class="fw-medium fs-14"><i class="ri-user-star-line me-1 text-info"></i>{{ $projectWork->supervisor }}</div>
            </div>
            @endif
            @if ($projectWork->resident)
            <div class="col-md-6 col-xl-3">
                <div class="text-muted fs-12 mb-1">Residente</div>
                <div class="fw-medium fs-14"><i class="ri-user-line me-1 text-info"></i>{{ $projectWork->resident }}</div>
            </div>
            @endif
            @if ($projectWork->contract_number)
            <div class="col-md-6 col-xl-3">
                <div class="text-muted fs-12 mb-1">Número de Contrato</div>
                <div class="fw-medium fs-14"><i class="ri-file-text-line me-1 text-primary"></i>{{ $projectWork->contract_number }}</div>
            </div>
            @endif
            @if ($projectWork->contract_value)
            <div class="col-md-6 col-xl-3">
                <div class="text-muted fs-12 mb-1">Valor del Contrato</div>
                <div class="fw-medium fs-14">
                    <i class="ri-money-dollar-circle-line me-1 text-success"></i>
                    {{ $projectWork->currency }}
                    {{ number_format((float) str_replace(',', '', $projectWork->contract_value), 2) }}
                </div>
            </div>
            @endif
            @if ($projectWork->contract_start_date || $projectWork->contract_end_date)
            <div class="col-md-6 col-xl-3">
                <div class="text-muted fs-12 mb-1">Fecha Inicio</div>
                <div class="fw-medium fs-14">
                    <i class="ri-calendar-line me-1 text-muted"></i>
                    {{ $projectWork->contract_start_date ? \Carbon\Carbon::parse($projectWork->contract_start_date)->format('d/m/Y') : '—' }}
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="text-muted fs-12 mb-1">Fecha Fin</div>
                <div class="fw-medium fs-14">
                    <i class="ri-calendar-check-line me-1 text-muted"></i>
                    {{ $projectWork->contract_end_date ? \Carbon\Carbon::parse($projectWork->contract_end_date)->format('d/m/Y') : '—' }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     ESTIMACIONES
══════════════════════════════════════════════════════════════════ --}}
@if (false)
@php
    $estimateTypeMap = [
        'estimacion'  => ['label' => 'Estimación',      'class' => 'bg-primary-subtle text-primary'],
        'nota_credito' => ['label' => 'Nota de Crédito', 'class' => 'bg-danger-subtle text-danger'],
        'anticipo'    => ['label' => 'Anticipo',        'class' => 'bg-info-subtle text-info'],
    ];
    $estimateCurrency = $projectWork->currency ?: 'MXN';
    $contractValue = (float) str_replace(',', '', $projectWork->contract_value ?? '0');
    $financialEstimates = $projectWork->estimates->whereIn('type', ['estimacion', 'nota_credito']);
    $estimatedWorkAmount = $projectWork->estimates
        ->where('type', 'estimacion')
        ->sum(fn ($estimate) => (float) $estimate->estimate_amount);
    $advanceAmount = $projectWork->estimates
        ->where('type', 'anticipo')
        ->sum(fn ($estimate) => (float) $estimate->estimate_amount);
    $creditNoteAmount = $projectWork->estimates
        ->where('type', 'nota_credito')
        ->sum(fn ($estimate) => (float) $estimate->estimate_amount);
    $tableEstimateTotal = $financialEstimates->sum(fn ($estimate) => (float) $estimate->estimate_amount);
    $tableLiquidTotal = $financialEstimates->sum(fn ($estimate) => $estimate->liquid_amount);
    $remainingToEstimate = $contractValue - $advanceAmount - $estimatedWorkAmount - $creditNoteAmount;
    $physicalProgress = $contractValue > 0
        ? ($estimatedWorkAmount / $contractValue) * 100
        : 0;
    $estimateStatusMap = [
        'pendiente' => ['label' => 'Pendiente', 'class' => 'bg-warning-subtle text-warning'],
        'pagada' => ['label' => 'Pagada', 'class' => 'bg-success-subtle text-success'],
    ];
@endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h5 class="card-title mb-0">
            <i class="ri-funds-line me-1 text-muted"></i> Estimaciones
        </h5>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateEstimate">
            <i class="ri-add-line me-1"></i> Registrar estimación
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                <thead class="bg-light-subtle">
                    <tr>
                        <th>EST</th>
                        <th>FACT</th>
                        <th>Fecha FACT</th>
                        <th class="text-end">IMP. EST</th>
                        <th class="text-end">ALCANCE LIQ.</th>
                        <th>DESCRIPCIÓN</th>
                        <th>FECHA PAGO</th>
                        <th>ESTATUS</th>
                        <th>Tipo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projectWork->estimates as $estimate)
                        @php
                            $type = $estimateTypeMap[$estimate->type] ?? ['label' => $estimate->type, 'class' => 'bg-secondary-subtle text-secondary'];
                            $status = $estimateStatusMap[$estimate->status] ?? ['label' => $estimate->status, 'class' => 'bg-secondary-subtle text-secondary'];
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $estimate->estimate_number }}</td>
                            <td>{{ $estimate->invoice_number ?: '—' }}</td>
                            <td class="text-muted fs-13">{{ $estimate->invoice_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-end fw-medium">{{ $estimateCurrency }} {{ number_format((float) $estimate->estimate_amount, 2) }}</td>
                            <td class="text-end fw-semibold text-success">{{ $estimateCurrency }} {{ number_format($estimate->liquid_amount, 2) }}</td>
                            <td class="text-wrap" style="min-width: 180px;">{{ $estimate->notes ?: '—' }}</td>
                            <td class="text-muted fs-13">{{ $estimate->payment_date?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="badge {{ $status['class'] }} py-1 px-2 fs-12">{{ $status['label'] }}</span></td>
                            <td><span class="badge {{ $type['class'] }} py-1 px-2 fs-12">{{ $type['label'] }}</span></td>
                            <td>
                                @if ((int) auth()->id() === (int) $estimate->created_by)
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-soft-primary btn-sm js-edit-estimate"
                                                title="Editar estimación"
                                                data-bs-toggle="modal" data-bs-target="#modalEditEstimate"
                                                data-url="{{ route('estimates.update', $estimate) }}"
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
                                        <form action="{{ route('estimates.destroy', $estimate) }}" method="POST" onsubmit="return confirm('¿Eliminar esta estimación?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar estimación">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted d-block text-end">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="ri-funds-line fs-24 d-block mb-1 opacity-50"></i>
                                Sin estimaciones registradas aún.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($projectWork->estimates->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="fw-semibold">Totales: Estimaciones y Notas de Crédito</td>
                            <td class="text-end fw-semibold">{{ $estimateCurrency }} {{ number_format($tableEstimateTotal, 2) }}</td>
                            <td class="text-end fw-semibold text-success">{{ $estimateCurrency }} {{ number_format($tableLiquidTotal, 2) }}</td>
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
    <div class="card-footer">
        <div class="row g-3">
            <div class="col-sm-6 col-xl">
                <div class="text-muted fs-12">Imp. contratado</div>
                <div class="fw-semibold">{{ $estimateCurrency }} {{ number_format($contractValue, 2) }}</div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="text-muted fs-12">Imp. anticipo</div>
                <div class="fw-semibold text-info">{{ $estimateCurrency }} {{ number_format($advanceAmount, 2) }}</div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="text-muted fs-12">Imp. NC</div>
                <div class="fw-semibold text-danger">{{ $estimateCurrency }} {{ number_format($creditNoteAmount, 2) }}</div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="text-muted fs-12">Imp. estimado</div>
                <div class="fw-semibold text-primary">{{ $estimateCurrency }} {{ number_format($estimatedWorkAmount, 2) }}</div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="text-muted fs-12">Por estimar</div>
                <div class="fw-semibold {{ $remainingToEstimate < 0 ? 'text-danger' : 'text-success' }}">{{ $estimateCurrency }} {{ number_format($remainingToEstimate, 2) }}</div>
            </div>
            <div class="col-sm-6 col-xl text-xl-end">
                <div class="text-muted fs-12">Avance físico</div>
                <div class="fw-semibold text-danger display-6">{{ number_format($physicalProgress, 2) }}%</div>
            </div>
        </div>
    </div>
</div>

@endif

{{-- ══════════════════════════════════════════════════════════════
     ÓRDENES DE COMPRA
══════════════════════════════════════════════════════════════════ --}}
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-file-list-3-line me-1 text-muted"></i> Órdenes de compra
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th># Orden</th>
                                <th>Descripción</th>
                                <th>Hitos</th>
                                <th>Importe total</th>
                                <th>Estatus</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $statusMap = [
                                    'emitida'    => ['label' => 'Emitida',    'class' => 'bg-info-subtle text-info'],
                                    'pendiente'  => ['label' => 'Pendiente',  'class' => 'bg-warning-subtle text-warning'],
                                    'autorizada' => ['label' => 'Autorizada', 'class' => 'bg-success-subtle text-success'],
                                ];
                            @endphp
                            @forelse ($projectWork->purchaseOrders as $order)
                                @php
                                    $os = $statusMap[$order->status] ?? ['label' => $order->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                    $tipoLabel = $order->type === 'materiales_servicios' ? 'Materiales / Servicios' : 'Mantenimiento';
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}" class="fw-semibold text-dark">
                                            OC #{{ $order->id }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fs-13">{{ $tipoLabel }}</span>
                                        @if ($order->supplier)
                                            <small class="text-muted d-block fs-11">
                                                <i class="ri-building-line me-1"></i>
                                                <a href="{{ route('suppliers.show', $order->supplier) }}" class="text-muted text-decoration-none">
                                                    {{ $order->supplier->rfc_name ?? $order->supplier->commercial_name ?? '—' }}
                                                </a>
                                            </small>
                                        @endif
                                    </td>
                                    <td>{{ $order->milestones_count }}</td>
                                    <td class="fw-semibold">
                                        {{ $order->currency }} {{ number_format($order->amount, 2) }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $os['class'] }} py-1 px-2 fs-12">{{ $os['label'] }}</span>
                                    </td>
                                    <td class="text-muted fs-12">{{ $order->created_at->format('d/m/Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-file-list-3-line fs-24 d-block mb-1 opacity-50"></i>
                                        Sin órdenes de compra registradas aún.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL — Registrar estimación --}}
@if (false)
<div class="modal fade" id="modalCreateEstimate" tabindex="-1" aria-labelledby="modalCreateEstimateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('estimates.store', $projectWork) }}" method="POST">
                @csrf
                <input type="hidden" name="estimate_form" value="create">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateEstimateLabel"><i class="ri-funds-line me-1"></i> Registrar estimación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('estimates._form', ['prefix' => 'create', 'estimate' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL — Editar estimación --}}
<div class="modal fade" id="modalEditEstimate" tabindex="-1" aria-labelledby="modalEditEstimateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formEditEstimate" action="" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="estimate_form" value="edit">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditEstimateLabel"><i class="ri-edit-line me-1"></i> Editar estimación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('estimates._form', ['prefix' => 'edit', 'estimate' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
@if (false)
<script>
document.addEventListener('DOMContentLoaded', function () {
    function amount(value) {
        return Number.parseFloat(String(value).replaceAll(',', '')) || 0;
    }

    function formatAmount(value) {
        return value.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalculateEstimate(prefix) {
        var modal = document.getElementById(prefix === 'create' ? 'modalCreateEstimate' : 'modalEditEstimate');
        var inputValue = function (field) {
            return amount(document.getElementById(prefix + '_' + field).value);
        };
        var setCalculatedValue = function (selector, value) {
            modal.querySelector(selector).value = formatAmount(value);
        };
        var estimateAmount = inputValue('estimate_amount');
        var returnedRetentionAmount = inputValue('returned_retention_amount');
        var vatAmount = estimateAmount * 0.16;
        var estimateTotal = estimateAmount + returnedRetentionAmount;
        var paymentsTotal = estimateTotal + vatAmount;
        var deductionsTotal = [
            'disfp_deduction',
            'apaee_deduction',
            'inc_retention_amount',
            'vat_retention_amount',
            'advance_amortization_amount',
            'advance_amortization_vat_amount',
            'funeral_expense_amount',
            'delay_penalty_amount',
        ].reduce(function (total, field) {
            return total + inputValue(field);
        }, 0);

        setCalculatedValue('.js-vat-amount', vatAmount);
        setCalculatedValue('.js-estimate-total', estimateTotal);
        setCalculatedValue('.js-payments-total', paymentsTotal);
        setCalculatedValue('.js-deductions-total', deductionsTotal);
        setCalculatedValue('.js-liquid-amount', paymentsTotal - deductionsTotal);
    }

    function setMoneyValue(input, value) {
        if (input.inputmask) {
            input.inputmask.setValue(value);
            return;
        }

        input.value = value;
    }

    ['create', 'edit'].forEach(function (prefix) {
        var modal = document.getElementById(prefix === 'create' ? 'modalCreateEstimate' : 'modalEditEstimate');
        modal.querySelectorAll('.js-estimate-money').forEach(function (input) {
            if (typeof Inputmask === 'undefined') return;

            new Inputmask('numeric', {
                radixPoint: '.',
                groupSeparator: ',',
                autoGroup: true,
                digits: 2,
                digitsOptional: true,
                allowMinus: false,
                rightAlign: false,
            }).mask(input);
        });
        modal.querySelectorAll('.js-payment-amount, .js-deduction-amount').forEach(function (input) {
            input.addEventListener('input', function () { recalculateEstimate(prefix); });
        });
        modal.querySelector('form').addEventListener('submit', function () {
            modal.querySelectorAll('.js-estimate-money').forEach(function (input) {
                if (input.inputmask) {
                    input.value = input.inputmask.unmaskedvalue();
                }
            });
        });
        recalculateEstimate(prefix);
    });

    document.querySelectorAll('.js-edit-estimate').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = document.getElementById('formEditEstimate');
            form.action = this.dataset.url;
            document.getElementById('edit_estimate_number').value = this.dataset.number;
            document.getElementById('edit_estimate_date').value = this.dataset.date;
            document.getElementById('edit_type').value = this.dataset.type;
            document.getElementById('edit_invoice_number').value = this.dataset.invoiceNumber || '';
            document.getElementById('edit_invoice_date').value = this.dataset.invoiceDate || '';
            document.getElementById('edit_payment_date').value = this.dataset.paymentDate || '';
            document.getElementById('edit_status').value = this.dataset.status || 'pendiente';
            [
                ['estimate_amount', 'estimateAmount'],
                ['returned_retention_amount', 'returnedRetentionAmount'],
                ['disfp_deduction', 'disfpDeduction'],
                ['apaee_deduction', 'apaeeDeduction'],
                ['inc_retention_amount', 'incRetentionAmount'],
                ['vat_retention_amount', 'vatRetentionAmount'],
                ['advance_amortization_amount', 'advanceAmortizationAmount'],
                ['advance_amortization_vat_amount', 'advanceAmortizationVatAmount'],
                ['funeral_expense_amount', 'funeralExpenseAmount'],
                ['delay_penalty_amount', 'delayPenaltyAmount'],
            ].forEach(function (field) {
                setMoneyValue(document.getElementById('edit_' + field[0]), this.dataset[field[1]] || 0);
            }, this);
            document.getElementById('edit_notes').value = this.dataset.notes || '';
            recalculateEstimate('edit');
        });
    });

    @if ($errors->any())
        var failedModal = document.getElementById('{{ old('estimate_form') === 'edit' ? 'modalEditEstimate' : 'modalCreateEstimate' }}');
        if (failedModal && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(failedModal).show();
        }
    @endif
});
</script>
@endif
@endpush
