<div class="modal fade" id="modalCreateAsset" tabindex="-1" aria-labelledby="modalCreateAssetLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('mobile_assets.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateAssetLabel">
                        <i class="ri-add-line me-1"></i> Nuevo Bien Móvil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="create_name" class="form-label fw-medium">
                                Nombre <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="create_name" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="create_folio" class="form-label fw-medium">Folio / Número Económico</label>
                            <input type="text" id="create_folio" name="folio"
                                   class="form-control @error('folio') is-invalid @enderror"
                                value="{{ old('folio') }}" placeholder="Ej. MOV-001">
                            @error('folio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                           <div class="col-md-6">
                            <label for="create_policy" class="form-label fw-medium">Póliza</label>
                            <input type="text" id="create_policy" name="policy"
                                class="form-control @error('policy') is-invalid @enderror"
                                value="{{ old('policy') }}"
                                placeholder="Ej. POL-12345">
                            @error('policy')<div class="invalid-feedback">{{ $message }}</div>@enderror
                           </div>

                           <div class="col-md-6">
                            <label for="create_card_number" class="form-label fw-medium">No. Tarjeta</label>
                            <input type="text" id="create_card_number" name="card_number"
                                class="form-control @error('card_number') is-invalid @enderror"
                                value="{{ old('card_number') }}"
                                placeholder="Ej. 12345678">
                            @error('card_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                           </div>

                           <div class="col-md-6">
                            <label for="create_milage" class="form-label fw-medium">Kilometraje</label>
                            <input type="text" id="create_milage" name="milage"
                                class="form-control @error('milage') is-invalid @enderror"
                                value="{{ old('milage') }}"
                                placeholder="Ej. 120000">
                            @error('milage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                           </div>

                        <div class="col-md-6">
                            <label for="create_brand" class="form-label fw-medium">Marca</label>
                            <input type="text" id="create_brand" name="brand"
                                   class="form-control @error('brand') is-invalid @enderror"
                                   value="{{ old('brand') }}">
                            @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label for="create_model" class="form-label fw-medium">Modelo</label>
                            <input type="text" id="create_model" name="model"
                                   class="form-control @error('model') is-invalid @enderror"
                                   value="{{ old('model') }}">
                            @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label for="create_year" class="form-label fw-medium">Año</label>
                            <input type="text" id="create_year" name="year"
                                   class="form-control @error('year') is-invalid @enderror"
                                   value="{{ old('year') }}" placeholder="2024">
                            @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="create_type" class="form-label fw-medium">Tipo</label>
                            <select id="create_type" name="type"
                                    class="form-select @error('type') is-invalid @enderror">
                                <option value="">— Seleccionar —</option>
                                <option value="parque_vehicular"  {{ old('type', $type) === 'parque_vehicular'  ? 'selected' : '' }}>Parque Vehicular</option>
                                <option value="maquinaria_pesada" {{ old('type', $type) === 'maquinaria_pesada' ? 'selected' : '' }}>Maquinaria Pesada</option>
                                <option value="semiremolque"      {{ old('type', $type) === 'semiremolque'      ? 'selected' : '' }}>SemiRemolque</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="create_operator" class="form-label fw-medium">Operador</label>
                            <input type="text" id="create_operator" name="operator"
                                   class="form-control @error('operator') is-invalid @enderror"
                                   value="{{ old('operator') }}">
                            @error('operator')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6" id="createPlatesField" style="display:none;">
                            <label for="create_plates" class="form-label fw-medium">Placas</label>
                            <input type="text" id="create_plates" name="plates"
                                   class="form-control @error('plates') is-invalid @enderror"
                                   value="{{ old('plates') }}" style="text-transform: uppercase;">
                            @error('plates')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="create_status" class="form-label fw-medium">Estatus</label>
                            <select id="create_status" name="status"
                                    class="form-select @error('status') is-invalid @enderror">
                                <option value="activo"     {{ old('status', 'activo') === 'activo'     ? 'selected' : '' }}>Activo</option>
                                <option value="vendido"    {{ old('status') === 'vendido'    ? 'selected' : '' }}>Vendido</option>
                                <option value="obsoleto"   {{ old('status') === 'obsoleto'   ? 'selected' : '' }}>Obsoleto</option>
                                <option value="reparacion" {{ old('status') === 'reparacion' ? 'selected' : '' }}>En reparación</option>
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
