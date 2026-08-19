<div class="row g-3">
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
    <div class="col-md-7">
        <label class="form-label" for="incentive_category">Concepto <span class="text-danger">*</span></label>
        <select id="incentive_category" class="form-select" name="category" required>
            <option value="overtime" @selected(old('category', $incentive?->category) === 'overtime')>Tiempo extra</option>
            <option value="day_off_exchange" @selected(old('category', $incentive?->category) === 'day_off_exchange')>Libranza</option>
            <option value="emergency" @selected(old('category', $incentive?->category) === 'emergency')>Emergencia</option>
        </select>
    </div>
    <div class="col-md-5">
        <label class="form-label" for="incentive_rate_type">Tipo <span class="text-danger">*</span></label>
        <select id="incentive_rate_type" class="form-select" name="rate_type" required>
            @foreach(['A', 'B', 'C', 'D'] as $rateType)
                <option value="{{ $rateType }}" @selected(old('rate_type', $incentive?->rate_type) === $rateType)>{{ $rateType }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-7">
        <label class="form-label" for="incentive_date">Fecha <span class="text-danger">*</span></label>
        <input id="incentive_date" class="form-control @error('incentive_date') is-invalid @enderror" name="incentive_date" type="date" value="{{ old('incentive_date', $incentive?->incentive_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
        @error('incentive_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-5">
        <label class="form-label" for="incentive_status">Estatus <span class="text-danger">*</span></label>
        <select id="incentive_status" class="form-select" name="status" required>
            <option value="active" @selected(old('status', $incentive?->status ?? 'active') === 'active')>Activo</option>
            <option value="cancelled" @selected(old('status', $incentive?->status) === 'cancelled')>Cancelado</option>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="incentive_notes">Notas</label>
        <textarea id="incentive_notes" class="form-control" name="notes" rows="3" maxlength="1000">{{ old('notes', $incentive?->notes) }}</textarea>
    </div>
</div>