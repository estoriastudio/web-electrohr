@php
    $context = $workerGroup?->id ?? 'new';
    $isCurrent = (string) old('form_context', $context) === (string) $context;
    $value = fn (string $field, $default = null) => $isCurrent ? old($field, $default) : $default;
@endphp

<input type="hidden" name="form_context" value="{{ $context }}">

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="worker_group_name_{{ $context }}">Nombre <span class="text-danger">*</span></label>
        <input id="worker_group_name_{{ $context }}" type="text" name="name" maxlength="255" required
               class="form-control {{ $isCurrent && $errors->has('name') ? 'is-invalid' : '' }}"
               value="{{ $value('name', $workerGroup?->name) }}">
        @if ($isCurrent)
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label" for="worker_group_status_{{ $context }}">Estado <span class="text-danger">*</span></label>
        <select id="worker_group_status_{{ $context }}" name="status" class="form-select" required>
            <option value="active" @selected($value('status', $workerGroup?->status ?? 'active') === 'active')>Activa</option>
            <option value="inactive" @selected($value('status', $workerGroup?->status) === 'inactive')>Inactiva</option>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="worker_group_notes_{{ $context }}">Notas</label>
        <textarea id="worker_group_notes_{{ $context }}" name="notes" rows="3" maxlength="1000"
                  class="form-control {{ $isCurrent && $errors->has('notes') ? 'is-invalid' : '' }}">{{ $value('notes', $workerGroup?->notes) }}</textarea>
        @if ($isCurrent)
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @endif
    </div>
</div>
