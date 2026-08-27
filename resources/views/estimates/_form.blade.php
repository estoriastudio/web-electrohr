@php
    $currency = $project->currency ?: 'MXN';
    $existingAllocations = $estimate
        ? $estimate->allocations->map(fn ($allocation) => [
            'project_work_id' => $allocation->project_work_id,
            'estimate_amount' => $allocation->estimate_amount,
        ])->all()
        : [];
    $allocationValues = collect(old('allocations', $existingAllocations))->keyBy('project_work_id');
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <label class="form-label fw-medium">Proyecto</label>
            <input type="text" class="form-control bg-light" value="{{ $project->name }}" readonly>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-medium">Moneda</label>
            <input type="text" class="form-control bg-light" value="{{ $currency }}" readonly>
        </div>
        <div class="col-md-4">
            <label for="{{ $prefix }}_estimate_number" class="form-label fw-medium">Núm. estimación <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('estimate_number') is-invalid @enderror"
                   id="{{ $prefix }}_estimate_number" name="estimate_number"
                   value="{{ old('estimate_number', $estimate?->estimate_number) }}" required>
            @error('estimate_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-5">
            <label for="{{ $prefix }}_type" class="form-label fw-medium">Tipo de estimación <span class="text-danger">*</span></label>
            <select class="form-select @error('type') is-invalid @enderror" id="{{ $prefix }}_type" name="type" required>
                <option value="estimacion" @selected(old('type', $estimate?->type) === 'estimacion')>Estimación</option>
                <option value="nota_credito" @selected(old('type', $estimate?->type) === 'nota_credito')>Nota de Crédito</option>
                <option value="anticipo" @selected(old('type', $estimate?->type) === 'anticipo')>Anticipo</option>
            </select>
            <div class="form-text">Estimación, Nota de Crédito o Anticipo.</div>
            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label for="{{ $prefix }}_estimate_date" class="form-label fw-medium">Fecha <span class="text-danger">*</span></label>
            <input type="date" class="form-control @error('estimate_date') is-invalid @enderror"
                   id="{{ $prefix }}_estimate_date" name="estimate_date"
                   value="{{ old('estimate_date', $estimate?->estimate_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
            @error('estimate_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label for="{{ $prefix }}_invoice_number" class="form-label fw-medium">Factura</label>
            <input type="text" class="form-control @error('invoice_number') is-invalid @enderror"
                   id="{{ $prefix }}_invoice_number" name="invoice_number"
                   value="{{ old('invoice_number', $estimate?->invoice_number) }}">
            @error('invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label for="{{ $prefix }}_invoice_date" class="form-label fw-medium">Fecha factura</label>
            <input type="date" class="form-control @error('invoice_date') is-invalid @enderror"
                   id="{{ $prefix }}_invoice_date" name="invoice_date"
                   value="{{ old('invoice_date', $estimate?->invoice_date?->format('Y-m-d')) }}">
            @error('invoice_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label for="{{ $prefix }}_payment_date" class="form-label fw-medium">Fecha pago</label>
            <input type="date" class="form-control @error('payment_date') is-invalid @enderror"
                   id="{{ $prefix }}_payment_date" name="payment_date"
                   value="{{ old('payment_date', $estimate?->payment_date?->format('Y-m-d')) }}">
            @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label for="{{ $prefix }}_status" class="form-label fw-medium">Estatus <span class="text-danger">*</span></label>
            <select class="form-select @error('status') is-invalid @enderror" id="{{ $prefix }}_status" name="status" required>
                <option value="pendiente" @selected(old('status', $estimate?->status ?? 'pendiente') === 'pendiente')>Pendiente</option>
                <option value="pagada" @selected(old('status', $estimate?->status) === 'pagada')>Pagada</option>
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="border-top pt-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="text-danger fw-semibold mb-0">Desglose por obra <span class="text-danger">*</span></h6>
                <div class="form-text">Selecciona las obras aplicables y asigna el importe correspondiente.</div>
            </div>
            <div class="text-end">
                <div class="text-muted fs-12">Pendiente por distribuir</div>
                <div class="fw-semibold js-allocation-remaining">{{ $currency }} 0.00</div>
            </div>
        </div>
        <div class="border rounded">
            @forelse ($project->works as $work)
                @php
                    $allocation = $allocationValues->get($work->id);
                    $isAllocated = $allocation !== null;
                @endphp
                <div class="row g-2 align-items-center p-2 {{ !$loop->last ? 'border-bottom' : '' }} js-estimate-allocation-row">
                    <div class="col-md-7">
                        <div class="form-check">
                            <input class="form-check-input js-estimate-allocation-toggle" type="checkbox"
                                   id="{{ $prefix }}_allocation_{{ $work->id }}"
                                   name="allocations[{{ $work->id }}][project_work_id]" value="{{ $work->id }}"
                                   data-work-id="{{ $work->id }}" @checked($isAllocated)>
                            <label class="form-check-label fw-medium" for="{{ $prefix }}_allocation_{{ $work->id }}">
                                {{ $work->name }}
                                @if ($work->status === 'inactive')
                                    <span class="badge bg-warning-subtle text-warning ms-1">Inactiva</span>
                                @endif
                            </label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">{{ $currency }}</span>
                            <input type="text" inputmode="decimal"
                                   class="form-control js-estimate-money js-allocation-amount"
                                   name="allocations[{{ $work->id }}][estimate_amount]"
                                   value="{{ $isAllocated ? $allocation['estimate_amount'] : '' }}"
                                   placeholder="0.00" @disabled(!$isAllocated)>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-muted text-center py-3">Registra al menos una obra antes de crear una estimación.</div>
            @endforelse
        </div>
        @error('allocations')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text text-danger d-none js-allocation-error"></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <h6 class="text-danger fw-semibold mb-3">Pagos</h6>
            <div class="row g-3">
                <div class="col-12">
                    <label for="{{ $prefix }}_estimate_amount" class="form-label fw-medium">Importe de la estimación <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">{{ $currency }}</span>
                           <input type="text" inputmode="decimal" class="form-control js-payment-amount js-estimate-money @error('estimate_amount') is-invalid @enderror"
                               id="{{ $prefix }}_estimate_amount" name="estimate_amount"
                               value="{{ old('estimate_amount', $estimate?->estimate_amount) }}" required>
                        @error('estimate_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12">
                    <label for="{{ $prefix }}_returned_retention_amount" class="form-label fw-medium">Dev. retención INC.</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ $currency }}</span>
                           <input type="text" inputmode="decimal" class="form-control js-payment-amount js-estimate-money @error('returned_retention_amount') is-invalid @enderror"
                               id="{{ $prefix }}_returned_retention_amount" name="returned_retention_amount"
                               value="{{ old('returned_retention_amount', $estimate?->returned_retention_amount) }}">
                        @error('returned_retention_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">IVA 16% estimación</label>
                    <div class="input-group"><span class="input-group-text">{{ $currency }}</span><input type="text" class="form-control bg-light js-vat-amount" readonly></div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Importe total (Est. + Dev.)</label>
                    <div class="input-group"><span class="input-group-text">{{ $currency }}</span><input type="text" class="form-control bg-light js-estimate-total" readonly></div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Total pagos + IVA</label>
                    <div class="input-group"><span class="input-group-text">{{ $currency }}</span><input type="text" class="form-control bg-light fw-semibold js-payments-total" readonly></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <h6 class="text-danger fw-semibold mb-3">Retenciones y/o Deducciones</h6>
            <div class="row g-3">
                @foreach ([
                    'disfp_deduction' => '0.005 D.I.S.F.P.',
                    'apaee_deduction' => '0.002 A.P.A.E.E.',
                    'inc_retention_amount' => 'Retención INC.',
                    'vat_retention_amount' => '% I.V.A. retención',
                    'advance_amortization_amount' => 'Amortización anticipo',
                    'advance_amortization_vat_amount' => 'IVA amortización anticipo',
                    'funeral_expense_amount' => 'Gastos sepelio',
                    'delay_penalty_amount' => 'Penalización por retraso',
                ] as $field => $label)
                    <div class="col-12">
                        <label for="{{ $prefix }}_{{ $field }}" class="form-label fw-medium">{{ $label }}</label>
                        <div class="input-group">
                            <span class="input-group-text">{{ $currency }}</span>
                            <input type="text" inputmode="decimal" class="form-control js-deduction-amount js-estimate-money @error($field) is-invalid @enderror"
                                id="{{ $prefix }}_{{ $field }}" name="{{ $field }}"
                                   value="{{ old($field, $estimate?->{$field}) }}">
                            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endforeach
                <div class="col-12">
                    <label class="form-label fw-medium">Total retenciones y/o deducciones</label>
                    <div class="input-group"><span class="input-group-text">{{ $currency }}</span><input type="text" class="form-control bg-light fw-semibold js-deductions-total" readonly></div>
                </div>
            </div>
        </div>
    </div>

    <div class="border-top mt-4 pt-3">
        <div class="row g-3 align-items-end">
            <div class="col-lg-6">
                <label class="form-label text-danger fw-semibold">Alcance líquido</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text">{{ $currency }}</span>
                    <input type="text" class="form-control bg-light text-success fw-semibold js-liquid-amount" readonly>
                </div>
            </div>
            <div class="col-lg-6">
                <label for="{{ $prefix }}_notes" class="form-label fw-medium">Descripción</label>
                <textarea class="form-control @error('notes') is-invalid @enderror" id="{{ $prefix }}_notes" name="notes" rows="2">{{ old('notes', $estimate?->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>