<form method="GET" action="{{ route('notifications.index') }}">
    <div class="card">
        <div class="card-body">
            <div class="row g-2 align-items-end">

                <div class="col-sm-4 col-lg-3">
                    <label class="form-label fs-13 mb-1">Módulo</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="Supplier"       @selected(request('type') === 'Supplier')>Proveedores</option>
                        <option value="PurchaseOrder"  @selected(request('type') === 'PurchaseOrder')>Órdenes de compra</option>
                    </select>
                </div>

                <div class="col-sm-4 col-lg-3">
                    <label class="form-label fs-13 mb-1">Acción</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="create" @selected(request('action') === 'create')>Creación</option>
                        <option value="update" @selected(request('action') === 'update')>Actualización</option>
                        <option value="delete" @selected(request('action') === 'delete')>Eliminación</option>
                    </select>
                </div>

                <div class="col-sm-4 col-lg-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="ri-search-line me-1"></i> Filtrar
                    </button>
                </div>

                @if(request()->hasAny(['type', 'action']))
                    <div class="col-sm-4 col-lg-2">
                        <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="ri-close-line me-1"></i> Limpiar
                        </a>
                    </div>
                @endif

            </div>
        </div>
    </div>
</form>
