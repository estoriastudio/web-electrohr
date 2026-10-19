{{-- Plazo de crédito del hito. Params: $idPrefix, $milestone (opcional). Se oculta si el hito es de contado o anticipo. --}}
@php
    $creditStart = old('credit_start', $milestone->credit_start ?? \App\Models\PurchaseOrderMilestone::CREDIT_START_INVOICE);
    $creditDays = old('credit_days', $milestone->credit_days ?? null);
@endphp
<div class="col-12 js-credit-terms">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-medium" for="{{ $idPrefix }}_credit_start">Inicio del plazo de crédito <span class="text-danger">*</span></label>
            <select class="form-select" name="credit_start" id="{{ $idPrefix }}_credit_start">
                <option value="invoice_validation" @selected($creditStart === 'invoice_validation')>Validación de factura</option>
                <option value="fulfillment_validated" @selected($creditStart === 'fulfillment_validated')>Cumplimiento validado</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-medium" for="{{ $idPrefix }}_credit_days">Plazo de crédito (días) <span class="text-danger">*</span></label>
            <input type="number" min="0" max="365" step="1" class="form-control" name="credit_days" id="{{ $idPrefix }}_credit_days"
                   value="{{ $creditDays }}" placeholder="Ej. 30">
        </div>
        <div class="col-12">
            <div class="form-text mt-0">
                La fecha de vencimiento se recalcula automáticamente (fecha de inicio + plazo) cuando se valida la factura o se confirma la entrega.
                Con "Cumplimiento validado" la factura no es requisito para autorizar el pago.
            </div>
        </div>
    </div>
</div>
@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-credit-terms').forEach(function (block) {
            var form = block.closest('form');
            if (!form) { return; }
            var condition = form.querySelector('[name="payment_condition"]');
            var advance = form.querySelector('[name="is_advance"][type="checkbox"]');
            function sync() {
                var visible = condition.value === 'credito' && !(advance && advance.checked);
                block.classList.toggle('d-none', !visible);
                block.querySelectorAll('input, select').forEach(function (field) { field.disabled = !visible; });
            }
            condition.addEventListener('change', sync);
            if (advance) { advance.addEventListener('change', sync); }
            sync();
        });
    });
    </script>
    @endpush
@endonce
