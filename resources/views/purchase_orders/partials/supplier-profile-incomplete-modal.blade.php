<div class="modal fade" id="modalSupplierProfileIncomplete" tabindex="-1" aria-labelledby="modalSupplierProfileIncompleteLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSupplierProfileIncompleteLabel">
                    <i class="ri-error-warning-line me-1 text-warning"></i> Perfil de proveedor incompleto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Completa los siguientes datos para fincar la orden de compra:</p>
                <ul class="mb-0 ps-3" id="supplierProfileMissingFields"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" id="chooseAnotherSupplier" data-bs-dismiss="modal">Elegir otro proveedor</button>
                <a href="#" class="btn btn-primary" id="supplierProfileLink">
                    <i class="ri-user-settings-line me-1"></i> Ir al perfil del proveedor
                </a>
            </div>
        </div>
    </div>
</div>