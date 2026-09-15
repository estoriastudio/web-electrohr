<div class="d-flex justify-content-end gap-2 mb-3">
    @if ($hasFilters)
        <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary" title="Limpiar filtros">
            <i class="ri-close-line"></i>
        </a>
    @endif
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#auditFiltersModal">
        <i class="ri-filter-3-line me-1"></i> Filtrar
    </button>
</div>

<div class="modal fade" id="auditFiltersModal" tabindex="-1" aria-labelledby="auditFiltersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="{{ route('notifications.index') }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="auditFiltersModalLabel"><i class="ri-filter-3-line me-1"></i> Filtrar actividad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Módulo</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="Supplier" @selected(request('type') === 'Supplier')>Proveedores</option>
                                <option value="PurchaseOrder" @selected(request('type') === 'PurchaseOrder')>Órdenes de compra</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Acción</label>
                            <select name="action" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                <option value="create" @selected(request('action') === 'create')>Creación</option>
                                <option value="update" @selected(request('action') === 'update')>Actualización</option>
                                <option value="delete" @selected(request('action') === 'delete')>Eliminación</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="auditUserId" class="form-label">Usuario</label>
                            <select id="auditUserId" name="user_id" class="form-select form-select-sm">
                                <option value="">Todos los usuarios</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Fecha inicio</label>
                            <input id="start_date" type="date" name="start_date" value="{{ request('start_date') }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">Fecha fin</label>
                            <input id="end_date" type="date" name="end_date" value="{{ request('end_date') }}" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i> Aplicar filtros</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
    <link href="{{ asset('assets/vendor/choices.js/public/assets/styles/choices.min.css') }}" rel="stylesheet" type="text/css">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendor/choices.js/public/assets/scripts/choices.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('auditFiltersModal');
            var userSelect = document.getElementById('auditUserId');
            var userChoices = null;

            modalEl.addEventListener('shown.bs.modal', function () {
                if (!userChoices) {
                    userChoices = new Choices(userSelect, {
                        searchEnabled: true,
                        searchPlaceholderValue: 'Buscar usuario...',
                        itemSelectText: '',
                        noResultsText: 'Sin resultados',
                        noChoicesText: 'Sin usuarios disponibles',
                    });
                }
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                if (userChoices) {
                    userChoices.destroy();
                    userChoices = null;
                }
            });
        });
    </script>
@endpush
