<div class="row g-3" data-incentive-form>
    <div class="col-12">
        <label class="form-label" for="incentive_worker_id">Trabajador <span class="text-danger">*</span></label>
        <select id="incentive_worker_id" name="worker_id" class="form-select @error('worker_id') is-invalid @enderror" required>
            <option value="">Selecciona un trabajador</option>
            @foreach($workers as $worker)
                <option value="{{ $worker->id }}" @selected((string) old('worker_id', $incentive?->worker_id) === (string) $worker->id)>{{ $worker->last_name }}, {{ $worker->first_name }}</option>
            @endforeach
        </select>
        @error('worker_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="incentive_category">Concepto <span class="text-danger">*</span></label>
        <select id="incentive_category" class="form-select" name="category" required>
            <option value="">Selecciona un concepto</option>
            <option value="incentive" @selected(old('category', $incentive?->category) === 'incentive')>Incentivo</option>
            <option value="overtime" @selected(old('category', $incentive?->category) === 'overtime')>Horas extra</option>
            <option value="day_off_exchange" @selected(old('category', $incentive?->category) === 'day_off_exchange')>Libranza</option>
        </select>
    </div>
    <div class="col-md-5 d-none" data-incentive-rate-field>
        <label class="form-label" for="incentive_rate_type">Tipo <span class="text-danger">*</span></label>
        <select id="incentive_rate_type" class="form-select" name="rate_type" required>
            @foreach(['A', 'B', 'C', 'D'] as $rateType)
                <option value="{{ $rateType }}" @selected(old('rate_type', $incentive?->rate_type) === $rateType)>{{ $rateType }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 d-none" data-overtime-field>
        <label class="form-label" for="incentive_overtime_hours">Cantidad de horas extra <span class="text-danger">*</span></label>
        <input id="incentive_overtime_hours" class="form-control @error('overtime_hours') is-invalid @enderror" name="overtime_hours" type="number" min="0.01" max="99.99" step="0.01" value="{{ old('overtime_hours', $incentive?->overtime_hours) }}">
        @error('overtime_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 d-none" data-overtime-field>
        <label class="form-label" for="incentive_overtime_hourly_rate">Valor de la hora extra <span class="text-danger">*</span></label>
        <input id="incentive_overtime_hourly_rate" class="form-control @error('overtime_hourly_rate') is-invalid @enderror" name="overtime_hourly_rate" type="number" min="0.01" max="99999.99" step="0.01" value="{{ old('overtime_hourly_rate', $incentive?->overtime_hourly_rate) }}">
        @error('overtime_hourly_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-7 d-none" data-incentive-details-field>
        <label class="form-label" for="incentive_date">Fecha <span class="text-danger">*</span></label>
        <input id="incentive_date" class="form-control @error('incentive_date') is-invalid @enderror" name="incentive_date" type="date" value="{{ old('incentive_date', $incentive?->incentive_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
        @error('incentive_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 d-none" data-incentive-details-field>
        <label class="form-label" for="incentive_notes">Notas</label>
        <textarea id="incentive_notes" class="form-control" name="notes" rows="3" maxlength="1000">{{ old('notes', $incentive?->notes) }}</textarea>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-incentive-form]').forEach(function (form) {
        const category = form.querySelector('[name="category"]');
        const rateField = form.querySelector('[data-incentive-rate-field]');
        const rateType = form.querySelector('[name="rate_type"]');
        const overtimeFields = form.querySelectorAll('[data-overtime-field]');
        const detailFields = form.querySelectorAll('[data-incentive-details-field]');

        const updateFields = function () {
            const hasCategory = category.value !== '';
            const isOvertime = category.value === 'overtime';
            const allowedTypes = category.value === 'day_off_exchange' ? ['A', 'B', 'C'] : ['A', 'B', 'C', 'D'];

            rateField.classList.toggle('d-none', !hasCategory || isOvertime);
            rateType.disabled = !hasCategory || isOvertime;
            overtimeFields.forEach(function (field) { field.classList.toggle('d-none', !isOvertime); });
            detailFields.forEach(function (field) { field.classList.toggle('d-none', !hasCategory); });

            Array.from(rateType.options).forEach(function (option) {
                option.hidden = !allowedTypes.includes(option.value);
            });

            if (!allowedTypes.includes(rateType.value)) {
                rateType.value = allowedTypes[0];
            }
        };

        category.addEventListener('change', updateFields);
        updateFields();
    });
});
</script>
@endpush