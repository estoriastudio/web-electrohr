<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="position_category_name_{{ $positionCategory?->id ?? 'new' }}">Nombre <span class="text-danger">*</span></label>
        <input id="position_category_name_{{ $positionCategory?->id ?? 'new' }}" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $positionCategory?->name) }}" maxlength="255" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="position_category_active_{{ $positionCategory?->id ?? 'new' }}">Estatus <span class="text-danger">*</span></label>
        <select id="position_category_active_{{ $positionCategory?->id ?? 'new' }}" name="active" class="form-select" required>
            <option value="1" @selected((string) old('active', $positionCategory?->active ?? true) === '1')>Activa</option>
            <option value="0" @selected((string) old('active', $positionCategory?->active) === '0')>Inactiva</option>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="position_category_notes_{{ $positionCategory?->id ?? 'new' }}">Notas</label>
        <textarea id="position_category_notes_{{ $positionCategory?->id ?? 'new' }}" name="notes" class="form-control" rows="3" maxlength="1000">{{ old('notes', $positionCategory?->notes) }}</textarea>
    </div>
</div>