<div class="row g-3">
    @foreach ($permissionGroups as $moduleLabel => $modulePermissions)
        <fieldset class="col-12 col-md-6">
            <legend class="fs-14 fw-semibold mb-2">{{ $moduleLabel }}</legend>
            <div class="d-flex flex-wrap gap-3">
                @foreach ($modulePermissions as $permissionName => $permissionLabel)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permissions[]"
                               value="{{ $permissionName }}" id="{{ $prefix }}_{{ $permissionName }}"
                               @checked(in_array($permissionName, $selected, true))>
                        <label class="form-check-label" for="{{ $prefix }}_{{ $permissionName }}">{{ $permissionLabel }}</label>
                    </div>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>