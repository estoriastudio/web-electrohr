{{-- Selector de roles + permisos directos opcionales. Requiere: $prefix, $selectedRoles, $selectedPermissions --}}
<div class="col-12" data-user-access>
    <label class="form-label fw-semibold" for="{{ $prefix }}_roles">Rol</label>
    <select class="form-select" name="roles[]" id="{{ $prefix }}_roles" multiple size="{{ min(max($roles->count(), 3), 6) }}"
            data-access-roles>
        @foreach ($roles as $role)
            <option value="{{ $role->name }}" @selected(in_array($role->name, $selectedRoles, true))>
                {{ $role->name }}
                {{ in_array($role->name, ['admin', 'supplier_portal_access', 'Recursos Humanos', 'Engineer'], true) ? '(Acceso)' : '(Perfil)' }}
            </option>
        @endforeach
    </select>
    <div class="form-text">Mantén presionada la tecla Ctrl (⌘ en Mac) para seleccionar más de un rol. (Acceso) también habilita áreas por rol (RR. HH., portal); (Perfil) solo aporta permisos.</div>

    <div class="mt-3 d-grid gap-2" data-access-role-alerts aria-live="polite"></div>

    <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" id="{{ $prefix }}_direct_toggle"
               data-access-direct-toggle @checked(count($selectedPermissions) > 0)>
        <label class="form-check-label fw-semibold" for="{{ $prefix }}_direct_toggle">Asignar permisos directos</label>
        <div class="form-text mt-0">Permisos adicionales solo para este usuario; se suman a los del rol.</div>
    </div>

    <div class="mt-3 {{ count($selectedPermissions) > 0 ? '' : 'd-none' }}" data-access-direct-panel>
        @include('users._permission_fields', ['prefix' => $prefix . '_perm', 'selected' => $selectedPermissions])
    </div>
</div>
