@php
    $partialDue = max(
        $milestone->due_date?->format('Y-m-d') ?? today()->toDateString(),
        $purchaseOrderPayments->max('payment_date')?->format('Y-m-d') ?? today()->toDateString()
    );
@endphp
<div class="modal fade" id="modalAdjustAmount{{ $milestone->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content js-adjust-milestone" method="POST"
              action="{{ route('purchase_orders.milestones.adjust_amount', [$purchaseOrder, $milestone]) }}"
              data-current-amount="{{ $milestone->effective_amount }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Modificar monto del Hito #{{ $milestonePosition }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted fs-13">Se conservan la descripción y las condiciones del hito actual. Su importe se guardará como monto fijo.</p>
                <div class="mb-3">
                    <label class="form-label">Nuevo monto ({{ $purchaseOrder->currency }})</label>
                    <input class="form-control" type="number" name="amount" min="0.01" step="0.01"
                           value="{{ $milestone->effective_amount }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Motivo obligatorio</label>
                    <textarea class="form-control" name="reason" maxlength="1000" required></textarea>
                </div>
                <fieldset class="js-partial-fields d-none" disabled>
                    <legend class="fs-15">Nuevo hito de parcialidad</legend>
                    <p class="fs-13">Saldo restante: <strong class="js-partial-amount"></strong>. La suma de los hitos no cambia.</p>
                    <div class="mb-3">
                        <label class="form-label">Descripción del nuevo hito</label>
                        <input class="form-control" name="partial[concept]" maxlength="255" value="{{ $milestone->concept }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Condición del nuevo hito</label>
                        <select class="form-select" name="partial[payment_condition]">
                            <option value="contado" @selected($milestone->payment_condition === 'contado')>Contra cumplimiento (contado)</option>
                            <option value="credito" @selected($milestone->payment_condition === 'credito')>Crédito</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="partial[is_advance]" value="1"
                               id="partialAdvance{{ $milestone->id }}" @checked($milestone->is_advance)>
                        <label class="form-check-label" for="partialAdvance{{ $milestone->id }}">Anticipo</label>
                    </div>
                    <div class="js-partial-credit">
                        <div class="mb-3">
                            <label class="form-label">Inicio del plazo de crédito</label>
                            <select class="form-select" name="partial[credit_start]">
                                <option value="invoice_validation" @selected($milestone->credit_start !== 'fulfillment_validated')>Validación de factura</option>
                                <option value="fulfillment_validated" @selected($milestone->credit_start === 'fulfillment_validated')>Cumplimiento validado</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Días de crédito</label>
                            <input class="form-control" type="number" name="partial[credit_days]" min="0" max="365"
                                   value="{{ $milestone->credit_days }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vencimiento provisional del nuevo hito</label>
                        <input class="form-control" type="date" name="partial[due_date]" value="{{ $partialDue }}" required>
                        <div class="form-text">En crédito se recalcula desde su propia factura o cumplimiento. No se heredan facturas ni entregas.</div>
                    </div>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar modificación</button>
            </div>
        </form>
    </div>
</div>
@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-adjust-milestone').forEach(function (form) {
            const amount = form.elements.amount;
            const fields = form.querySelector('.js-partial-fields');
            const condition = form.querySelector('[name="partial[payment_condition]"]');
            const advance = form.querySelector('[name="partial[is_advance]"]');
            const credit = form.querySelector('.js-partial-credit');
            function sync() {
                const remainder = Math.round(Number(form.dataset.currentAmount) * 100) - Math.round(Number(amount.value) * 100);
                fields.disabled = remainder <= 0;
                fields.classList.toggle('d-none', remainder <= 0);
                form.querySelector('.js-partial-amount').textContent = (remainder / 100).toFixed(2);
                const isCredit = condition.value === 'credito' && !advance.checked;
                credit.classList.toggle('d-none', !isCredit);
                credit.querySelectorAll('input, select').forEach(function (field) {
                    field.disabled = !isCredit;
                    field.required = isCredit;
                });
            }
            amount.addEventListener('input', sync);
            condition.addEventListener('change', sync);
            advance.addEventListener('change', sync);
            sync();
        });
    });
    </script>
    @endpush
@endonce
