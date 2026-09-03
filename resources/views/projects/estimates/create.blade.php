@extends('layouts.app')

@section('page_title', 'Registrar estimación')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyectos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
    <li class="breadcrumb-item active">Registrar estimación</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-semibold mb-1"><i class="ri-funds-line me-1 text-muted"></i> Registrar estimación</h5>
        <div class="text-muted fs-13">{{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.show', $project) }}" class="btn btn-light btn-sm">
        <i class="ri-arrow-left-line me-1"></i> Volver al proyecto
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-semibold mb-1">No se pudo registrar la estimación. Revisa los campos marcados.</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <form id="createEstimateForm" action="{{ route('projects.estimates.store', $project) }}" method="POST">
        @csrf
        <div class="card-body">
            @include('estimates._form', ['prefix' => 'create', 'estimate' => null])
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('projects.show', $project) }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Registrar estimación</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('createEstimateForm');

    function amount(value) { return Number.parseFloat(String(value).replaceAll(',', '')) || 0; }
    function formatAmount(value) { return value.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function recalculateEstimate() {
        var inputValue = function (field) { return amount(document.getElementById('create_' + field).value); };
        var setCalculatedValue = function (selector, value) { form.querySelector(selector).value = formatAmount(value); };
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

    form.querySelectorAll('.js-estimate-money').forEach(function (input) {
        if (typeof Inputmask === 'undefined') return;
        new Inputmask('numeric', {
            radixPoint: '.', groupSeparator: ',', autoGroup: true, digits: 2,
            digitsOptional: true, allowMinus: false, rightAlign: false,
        }).mask(input);
    });
    form.querySelectorAll('.js-payment-amount, .js-deduction-amount').forEach(function (input) {
        input.addEventListener('input', recalculateEstimate);
    });
    form.addEventListener('submit', function () {
        form.querySelectorAll('.js-estimate-money').forEach(function (input) {
            if (input.inputmask) input.value = input.inputmask.unmaskedvalue();
        });
    });
    recalculateEstimate();
});
</script>
@endpush