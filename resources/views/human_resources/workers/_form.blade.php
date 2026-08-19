@php($worker = $worker ?? null)
<div class="row g-3">
    <div class="col-12">
        <p class="text-muted fs-12 mb-0"><span class="text-danger">*</span> Campos obligatorios para registrar al trabajador.</p>
    </div>
    <div class="col-12 border-top pt-3 mt-2">
        <h6 class="mb-1"><i class="ri-user-3-line me-1 text-primary"></i>Identidad</h6>
        <span class="text-muted fs-12">Datos básicos para identificar al trabajador.</span>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="first_name">Nombre(s) <span class="text-danger">*</span></label>
        <input id="first_name" name="first_name" type="text" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $worker?->first_name) }}" required>
        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="last_name">Apellidos <span class="text-danger">*</span></label>
        <input id="last_name" name="last_name" type="text" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $worker?->last_name) }}" required>
        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="employee_code">No. de cuenta bancaria <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="employee_code" name="employee_code" type="text" class="form-control @error('employee_code') is-invalid @enderror" value="{{ old('employee_code', $worker?->employee_code) }}">
        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="nickname">Apodo <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="nickname" name="nickname" type="text" class="form-control" value="{{ old('nickname', $worker?->nickname) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="profile_photo">Fotografía de perfil <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png" class="form-control @error('profile_photo') is-invalid @enderror">
        @error('profile_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 border-top pt-3 mt-2">
        <h6 class="mb-1"><i class="ri-briefcase-4-line me-1 text-primary"></i>Información laboral</h6>
        <span class="text-muted fs-12">Define la relación laboral inicial.</span>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="hire_date">Fecha de alta <span class="text-danger">*</span></label>
        <input id="hire_date" name="hire_date" type="date" class="form-control @error('hire_date') is-invalid @enderror" value="{{ old('hire_date', $worker?->hire_date?->format('Y-m-d')) }}" required>
        @error('hire_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="weekly_salary">Sueldo semanal <span class="text-danger">*</span></label>
        <input id="weekly_salary" name="weekly_salary" type="number" min="0" step="0.01" class="form-control @error('weekly_salary') is-invalid @enderror" value="{{ old('weekly_salary', $worker?->weekly_salary) }}" required>
        @error('weekly_salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="position_category_id">Puesto <span class="text-muted fw-normal">(opcional)</span></label>
        <select id="position_category_id" name="position_category_id" class="form-select @error('position_category_id') is-invalid @enderror">
            <option value="">Sin asignar</option>
            @foreach ($positionCategories as $positionCategory)
                <option value="{{ $positionCategory->id }}" @selected((string) old('position_category_id', $worker?->position_category_id) === (string) $positionCategory->id)>{{ $positionCategory->name }}</option>
            @endforeach
        </select>
        @error('position_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="payment_type">Tipo de pago <span class="text-danger">*</span></label>
        <select id="payment_type" name="payment_type" class="form-select @error('payment_type') is-invalid @enderror" required>
            <option value="salaried" @selected(old('payment_type', $worker?->payment_type ?? 'salaried') === 'salaried')>Sueldo semanal</option>
            <option value="piecework" @selected(old('payment_type', $worker?->payment_type) === 'piecework')>Destajo</option>
        </select>
        @error('payment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label d-block">Perfil del trabajador</label>
        <div class="form-check mt-2">
            <input id="is_dc5" name="is_dc5" type="checkbox" value="1" class="form-check-input" @checked(old('is_dc5', $worker?->is_dc5))>
            <label class="form-check-label" for="is_dc5">DC5 (Agente capacitado)</label>
        </div>
    </div>
    <div class="col-12 border-top pt-3 mt-2">
        <h6 class="mb-1"><i class="ri-file-user-line me-1 text-primary"></i>Identificación y vigencias</h6>
        <span class="text-muted fs-12">Información documental disponible al momento del registro.</span>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="rfc">RFC <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="rfc" name="rfc" type="text" class="form-control" value="{{ old('rfc', $worker?->rfc) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="curp">CURP <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="curp" name="curp" type="text" class="form-control" value="{{ old('curp', $worker?->curp) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="birth_date">Fecha de nacimiento <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="birth_date" name="birth_date" type="date" class="form-control" value="{{ old('birth_date', $worker?->birth_date?->format('Y-m-d')) }}">
    </div>
    <div class="col-12 border-top pt-3 mt-2">
        <h6 class="mb-1"><i class="ri-contacts-line me-1 text-primary"></i>Contacto y datos complementarios</h6>
        <span class="text-muted fs-12">Información útil para atención y administración.</span>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="emergency_contact_name">Contacto de emergencia <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="emergency_contact_name" name="emergency_contact_name" type="text" class="form-control" value="{{ old('emergency_contact_name', $worker?->emergency_contact_name) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="emergency_contact_phone">Teléfono de emergencia <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="emergency_contact_phone" name="emergency_contact_phone" type="text" class="form-control" value="{{ old('emergency_contact_phone', $worker?->emergency_contact_phone) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="nss">NSS <span class="text-muted fw-normal">(opcional)</span></label>
        <input id="nss" name="nss" type="text" class="form-control" value="{{ old('nss', $worker?->nss) }}">
    </div>
    <div class="col-12">
        <label class="form-label" for="notes">Notas <span class="text-muted fw-normal">(opcional)</span></label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $worker?->notes) }}</textarea>
    </div>
</div>