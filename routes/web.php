<?php

/* Controladores */
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierContactController;
use App\Http\Controllers\SupplierLocationController;
use App\Http\Controllers\MobileAssetController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderMilestoneController;
use App\Http\Controllers\PurchaseOrderInvoiceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectWorkController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\MaterialVoucherController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\ConceptController;
use App\Http\Controllers\ConceptCategoryController;

use App\Http\Controllers\PaymentController;

/* Ayudantes */
use Illuminate\Support\Facades\Route;

/* Rutas protegidas por autenticación */
Route::namespace('App\Http\Controllers')->group(function () {
    Route::group(['middleware' => ['auth']], function () {
        Route::get('/', 'AdminController@dashboard')->name('dashboard');
        Route::get('/configuracion', 'AdminController@settings')->name('settings');

        // Perfil propio — accesible para todos los roles autenticados
        Route::get('/usuarios/{user}', [UserController::class, 'show'])->name('usuarios.show');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');

        // ── Solo admin ────────────────────────────────────────────────────────

        // Proveedores
        Route::middleware('role:admin|Orden de compra')->group(function () {
            Route::get('proveedores/export', [SupplierController::class, 'export'])->name('suppliers.export');
            Route::post('proveedores/import', [SupplierController::class, 'import'])->name('suppliers.import');
            Route::resource('/proveedores', SupplierController::class, [
                'names' => [
                    'index'   => 'suppliers.index',
                    'create'  => 'suppliers.create',
                    'store'   => 'suppliers.store',
                    'show'    => 'suppliers.show',
                    'edit'    => 'suppliers.edit',
                    'update'  => 'suppliers.update',
                    'destroy' => 'suppliers.destroy',
                ],
                'parameters' => ['proveedores' => 'supplier'],
            ]);
            Route::put('proveedores/{supplier}/informacion', [SupplierController::class, 'updateInfo'])->name('suppliers.update_info');

            Route::resource('proveedores.contactos', SupplierContactController::class, [
                'names' => [
                    'store'   => 'supplier_contacts.store',
                    'update'  => 'supplier_contacts.update',
                    'destroy' => 'supplier_contacts.destroy',
                ],
                'parameters' => ['proveedores' => 'supplier', 'contactos' => 'contact'],
            ])->only(['store', 'update', 'destroy']);

            Route::get('proveedores/{supplier}/contacto-principal', [SupplierContactController::class, 'primaryJson'])
                 ->name('supplier_contacts.primary_json');

            Route::resource('proveedores.sucursales', SupplierLocationController::class, [
                'names' => [
                    'store'   => 'supplier_locations.store',
                    'update'  => 'supplier_locations.update',
                    'destroy' => 'supplier_locations.destroy',
                ],
                'parameters' => ['proveedores' => 'supplier', 'sucursales' => 'location'],
            ])->only(['store', 'update', 'destroy']);
        });

        // Bienes Móviles
        Route::middleware('role:admin|Moviles')->group(function () {
            Route::get('bienes-mobiles/export', [MobileAssetController::class, 'export'])->name('mobile_assets.export');
            Route::post('bienes-mobiles/import', [MobileAssetController::class, 'import'])->name('mobile_assets.import');
            Route::resource('/bienes-mobiles', MobileAssetController::class, [
                'names' => [
                    'index'   => 'mobile_assets.index',
                    'create'  => 'mobile_assets.create',
                    'store'   => 'mobile_assets.store',
                    'show'    => 'mobile_assets.show',
                    'edit'    => 'mobile_assets.edit',
                    'update'  => 'mobile_assets.update',
                    'destroy' => 'mobile_assets.destroy',
                ],
                'parameters' => ['bienes-mobiles' => 'mobile_asset'],
            ]);

            // Fotos del bien móvil (upload individual por slot)
            Route::post('bienes-mobiles/{mobile_asset}/foto/{slot}', [MobileAssetController::class, 'uploadPhoto'])
                 ->name('mobile_assets.photo.upload')
                 ->where('slot', '[123]');
            Route::delete('bienes-mobiles/{mobile_asset}/foto/{slot}', [MobileAssetController::class, 'deletePhoto'])
                 ->name('mobile_assets.photo.delete')
                 ->where('slot', '[123]');

            // Checklist documental del bien móvil
            Route::patch('bienes-mobiles/{mobile_asset}/documentos/{docType}', [MobileAssetController::class, 'updateDocument'])
                 ->name('mobile_assets.document.update');

            // Bitácora de mantenimiento
            Route::post('bienes-mobiles/{mobile_asset}/bitacora', [MaintenanceLogController::class, 'store'])
                 ->name('maintenance_logs.store');
            Route::delete('bienes-mobiles/{mobile_asset}/bitacora/{maintenanceLog}', [MaintenanceLogController::class, 'destroy'])
                 ->name('maintenance_logs.destroy');
        });

        // Proyectos
        Route::get('/proyectos/{project}/obras-json', [ProjectController::class, 'worksJson'])->name('projects.works_json');

        Route::middleware('role:admin|Proyectos|Solmat')->group(function () {
            Route::resource('/proyectos', ProjectController::class, [
                'names' => [
                    'index'   => 'projects.index',
                    'create'  => 'projects.create',
                    'store'   => 'projects.store',
                    'show'    => 'projects.show',
                    'edit'    => 'projects.edit',
                    'update'  => 'projects.update',
                    'destroy' => 'projects.destroy',
                ],
                'parameters' => ['proyectos' => 'project'],
            ]);

            Route::post('/proyectos/{project}/documentos/{docType}', [ProjectController::class, 'uploadDocument'])
                ->name('projects.document.upload');

            Route::post('/proyectos/{project}/documentos/{docType}/chunk/init', [ProjectController::class, 'initChunkUpload'])
                ->name('projects.document.chunk.init');
            Route::post('/proyectos/{project}/documentos/{docType}/chunk/upload', [ProjectController::class, 'uploadChunk'])
                ->name('projects.document.chunk.upload');
            Route::post('/proyectos/{project}/documentos/{docType}/chunk/finalize', [ProjectController::class, 'finalizeChunkUpload'])
                ->name('projects.document.chunk.finalize');
            Route::post('/proyectos/{project}/documentos/{docType}/chunk/abort', [ProjectController::class, 'abortChunkUpload'])
                ->name('projects.document.chunk.abort');

            Route::post('/proyectos/import', [ProjectController::class, 'import'])
                ->name('projects.import');

            Route::resource('/obras', ProjectWorkController::class, [
                'names' => [
                    'index'   => 'project_works.index',
                    'create'  => 'project_works.create',
                    'store'   => 'project_works.store',
                    'show'    => 'project_works.show',
                    'edit'    => 'project_works.edit',
                    'update'  => 'project_works.update',
                    'destroy' => 'project_works.destroy',
                ],
                'parameters' => ['obras' => 'project_work'],
            ]);
        });

        // Conceptos (catálogo) — búsqueda JSON accesible a admin|Orden de compra
        Route::get('/conceptos/buscar', [ConceptController::class, 'search'])->name('concepts.search');
        Route::get('/categorias-conceptos/{conceptCategory}/subcategorias-json', [ConceptCategoryController::class, 'subcategoriesJson'])->name('concept_categories.subcategories_json');


        // Conceptos (catálogo) — CRUD solo admin
        Route::middleware('role:admin|Solmat')->group(function () {
            Route::post('/conceptos/import', [ConceptController::class, 'import'])->name('concepts.import');
            Route::resource('/conceptos', ConceptController::class, [
                'names' => [
                    'index'   => 'concepts.index',
                    'store'   => 'concepts.store',
                    'update'  => 'concepts.update',
                    'destroy' => 'concepts.destroy',
                ],
                'parameters' => ['conceptos' => 'concept'],
            ])->only(['index', 'store', 'update', 'destroy']);

            // Categorías de Conceptos
            Route::get('/categorias-conceptos', [ConceptCategoryController::class, 'index'])->name('concept_categories.index');
            Route::post('/categorias-conceptos', [ConceptCategoryController::class, 'store'])->name('concept_categories.store');
            Route::put('/categorias-conceptos/{conceptCategory}', [ConceptCategoryController::class, 'update'])->name('concept_categories.update');
            Route::delete('/categorias-conceptos/{conceptCategory}', [ConceptCategoryController::class, 'destroy'])->name('concept_categories.destroy');
            Route::put('/categorias-conceptos/{conceptCategory}/compradores', [ConceptCategoryController::class, 'syncUsers'])->name('concept_categories.sync_users');
            Route::post('/categorias-conceptos/{conceptCategory}/subcategorias', [ConceptCategoryController::class, 'storeSubcategory'])->name('concept_categories.subcategories.store');
            Route::put('/categorias-conceptos/{conceptCategory}/subcategorias/{subcategory}', [ConceptCategoryController::class, 'updateSubcategory'])->name('concept_categories.subcategories.update');
            Route::delete('/categorias-conceptos/{conceptCategory}/subcategorias/{subcategory}', [ConceptCategoryController::class, 'destroySubcategory'])->name('concept_categories.subcategories.destroy');
        });

        // Usuarios (gestión) y Roles
        Route::middleware('role:admin')->group(function () {
            Route::resource('usuarios', UserController::class, [
                'names' => [
                    'index'   => 'usuarios.index',
                    'create'  => 'usuarios.create',
                    'store'   => 'usuarios.store',
                    'edit'    => 'usuarios.edit',
                    'destroy' => 'usuarios.destroy',
                ],
                'parameters' => ['usuarios' => 'user'],
            ])->except(['show', 'update']);

            Route::post('/roles', 'UserController@storeRole')->name('roles.store');
            Route::delete('/roles/{role}', 'UserController@destroyRole')->name('roles.destroy');
        });

        // Auditoría / Notificaciones
        Route::middleware('role:admin')->group(function () {
            Route::get('/auditoria', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/auditoria/marcar-leidas', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
        });

        // ── Admin + Payments + Orders ─────────────────────────────────────────

        // Órdenes de Compra — lectura: admin, Pagos, Orden de compra
        Route::middleware('role:admin|Pagos|Orden de compra')->group(function () {
            Route::get('/ordenes-de-compra', [PurchaseOrderController::class, 'index'])->name('purchase_orders.index');
            Route::get('/ordenes-de-compra/create', [PurchaseOrderController::class, 'create'])->name('purchase_orders.create');
            Route::get('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase_orders.show');
        });

        // PDF de OC — incluye Solmat para descarga desde trazabilidad
        Route::middleware('role:admin|Pagos|Orden de compra|Solmat')->group(function () {
            Route::get('/ordenes-de-compra/{purchase_order}/pdf', [PurchaseOrderController::class, 'downloadPdf'])->name('purchase_orders.pdf');
        });

        // Órdenes de Compra — escritura: admin, Orden de compra
        Route::middleware('role:admin|Orden de compra')->group(function () {
            Route::get('/ordenes-de-compra/create', [PurchaseOrderController::class, 'create'])->name('purchase_orders.create');
            Route::get('/ordenes-de-compra/crear-desde/{purchaseRequest}', [PurchaseOrderController::class, 'createFromSolcom'])->name('purchase_orders.create_from_solcom');
            Route::post('/ordenes-de-compra', [PurchaseOrderController::class, 'store'])->name('purchase_orders.store');
            Route::get('/ordenes-de-compra/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase_orders.edit');
            Route::put('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'update'])->name('purchase_orders.update');
            Route::patch('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'update']);
            Route::delete('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'destroy'])->name('purchase_orders.destroy');
            // Ítems (conceptos)
            Route::post('/ordenes-de-compra/{purchase_order}/items', [PurchaseOrderController::class, 'storeItem'])->name('purchase_orders.items.store');
            Route::patch('/ordenes-de-compra/{purchase_order}/items/{item}', [PurchaseOrderController::class, 'updateItem'])->name('purchase_orders.items.update');
            Route::delete('/ordenes-de-compra/{purchase_order}/items/{item}', [PurchaseOrderController::class, 'destroyItem'])->name('purchase_orders.items.destroy');
            // Observaciones
            Route::post('/ordenes-de-compra/{purchase_order}/notes', [PurchaseOrderController::class, 'storeObservation'])->name('purchase_orders.notes.store');
            Route::patch('/ordenes-de-compra/{purchase_order}/notes/{noteIndex}', [PurchaseOrderController::class, 'updateObservation'])->name('purchase_orders.notes.update');
            Route::delete('/ordenes-de-compra/{purchase_order}/notes/{noteIndex}', [PurchaseOrderController::class, 'destroyObservation'])->name('purchase_orders.notes.destroy');
        });

        // Hitos — lectura: admin, Pagos
        Route::middleware('role:admin|Pagos')->group(function () {
            Route::get('/hitos', [PurchaseOrderMilestoneController::class, 'index'])->name('milestones.index');
            Route::get('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'show'])->name('milestones.show');
        });

        // Hitos — escritura: admin, Pagos
        Route::middleware('role:admin|Orden de compra|Pagos')->group(function () {
            Route::get('/hitos/create', [PurchaseOrderMilestoneController::class, 'create'])->name('milestones.create');
            Route::post('/hitos', [PurchaseOrderMilestoneController::class, 'store'])->name('milestones.store');
            Route::get('/hitos/{purchaseOrderMilestone}/edit', [PurchaseOrderMilestoneController::class, 'edit'])->name('milestones.edit');
            Route::put('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'update'])->name('milestones.update');
            Route::patch('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'update']);
            Route::delete('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'destroy'])->name('milestones.destroy');
        });

        // ── Admin + Payments ──────────────────────────────────────────────────

        // Pagos
        Route::middleware('role:admin|Pagos')->group(function () {
            Route::get('/pagos/autorizar', [PaymentController::class, 'index'])->name('payments.index');
            Route::resource('/pagos', PaymentController::class)->except(['index'])->names([
                'create'  => 'payments.create',
                'store'   => 'payments.store',
                'show'    => 'payments.show',
                'edit'    => 'payments.edit',
                'update'  => 'payments.update',
                'destroy' => 'payments.destroy',
            ])->parameters(['pagos' => 'payment']);
        });

        // Autorización de Órdenes de Compra — solo admin
        Route::middleware('role:admin')->group(function () {
            Route::patch('/ordenes-de-compra/{purchase_order}/autorizar', [PurchaseOrderController::class, 'approve'])->name('purchase_orders.approve');
        });

        // Modo interactivo (swipable) — solo admin
        Route::middleware('role:admin')->group(function () {
            Route::get('/pagos/interactivo/autorizar', [PaymentController::class, 'interactive'])->name('payments.interactive');
            Route::post('/pagos/{payment}/swipe', [PaymentController::class, 'swipe'])->name('payments.swipe');
        });

        // Facturas de Órdenes de Compra
        Route::middleware('role:admin|Pagos|Recepción|Orden de compra')->group(function () {
            Route::get('/facturas/alta', [PaymentController::class, 'altaFacturas'])->name('payments.alta_facturas');
            Route::post('/facturas', [PurchaseOrderInvoiceController::class, 'store'])->name('invoices.store');
            Route::get('/facturas/{invoice}/download', [PurchaseOrderInvoiceController::class, 'download'])->name('invoices.download');
            Route::delete('/facturas/{invoice}', [PurchaseOrderInvoiceController::class, 'destroy'])->name('invoices.destroy');
        });

        // Contrarecibo PDF — accesible para admin, Pagos y Orden de compra
        Route::middleware('role:admin|Pagos|Orden de compra')->group(function () {
            Route::get('/pagos/{payment}/contrarecibo', [PaymentController::class, 'contrarecibo'])->name('payments.contrarecibo');
            Route::get('/pagos/{payment}/comprobante-spei', [PaymentController::class, 'downloadSpeiReceipt'])->name('payments.spei_receipt.download');
        });

        // AJAX: hitos de una OC (para Alta de Facturas)
        Route::middleware('role:admin|Pagos|Orden de compra')->group(function () {
            Route::get('/ordenes-de-compra/{purchaseOrder}/hitos-json', [PurchaseOrderMilestoneController::class, 'forOrder'])->name('milestones.for_order');
        });

        // ── SOLMAT (Solicitudes de Material) ──────────────────────────────────
        Route::middleware('role:admin|Solmat')->group(function () {
            // AJAX lookup (debe ir antes del resource para evitar conflicto con {material_request})
            Route::get('/solicitudes-material/buscar', [MaterialRequestController::class, 'jsonByFolio'])
                 ->name('material_requests.lookup');

            Route::resource('/solicitudes-material', MaterialRequestController::class, [
                'names'      => [
                    'index'   => 'material_requests.index',
                    'create'  => 'material_requests.create',
                    'store'   => 'material_requests.store',
                    'edit'    => 'material_requests.edit',
                    'update'  => 'material_requests.update',
                    'destroy' => 'material_requests.destroy',
                ],
                'parameters' => ['solicitudes-material' => 'materialRequest'],
            ])->except(['show']);

            Route::post('/solicitudes-material/{materialRequest}/items',
                        [MaterialRequestController::class, 'storeItem'])
                 ->name('material_requests.items.store');

            Route::delete('/solicitudes-material/{materialRequest}/items/{item}',
                          [MaterialRequestController::class, 'destroyItem'])
                 ->name('material_requests.items.destroy');

            Route::post('/solicitudes-material/{materialRequest}/notes',
                        [MaterialRequestController::class, 'storeObservation'])
                 ->name('material_requests.notes.store');

              Route::patch('/solicitudes-material/{materialRequest}/notes/{noteIndex}',
                        [MaterialRequestController::class, 'updateObservation'])
                  ->name('material_requests.notes.update');

              Route::delete('/solicitudes-material/{materialRequest}/notes/{noteIndex}',
                         [MaterialRequestController::class, 'destroyObservation'])
                  ->name('material_requests.notes.destroy');

            Route::get('/solicitudes-material/{materialRequest}/pdf',
                       [MaterialRequestController::class, 'downloadPdf'])
                 ->name('material_requests.pdf');

            Route::post('/solicitudes-material/{materialRequest}/send-to-warehouse',
                        [MaterialRequestController::class, 'sendToWarehouse'])
                 ->name('material_requests.send_to_warehouse');
        });

        // ── Vales de Material ───────────────────────────────────────────────
        Route::middleware('role:admin|Pagos|Proveedor')->group(function () {
            Route::resource('/vales-material', MaterialVoucherController::class, [
                'names'      => [
                    'index'   => 'material_vouchers.index',
                    'create'  => 'material_vouchers.create',
                    'store'   => 'material_vouchers.store',
                    'show'    => 'material_vouchers.show',
                    'edit'    => 'material_vouchers.edit',
                    'update'  => 'material_vouchers.update',
                    'destroy' => 'material_vouchers.destroy',
                ],
                'parameters' => ['vales-material' => 'materialVoucher'],
            ]);

            Route::post('/vales-material/{materialVoucher}/items',
                [MaterialVoucherController::class, 'storeItem'])
                ->name('material_vouchers.items.store');

            Route::delete('/vales-material/{materialVoucher}/items/{item}',
                [MaterialVoucherController::class, 'destroyItem'])
                ->name('material_vouchers.items.destroy');

            Route::post('/vales-material/{materialVoucher}/notes',
                [MaterialVoucherController::class, 'storeObservation'])
                ->name('material_vouchers.notes.store');

            Route::post('/vales-material/{materialVoucher}/authorize',
                [MaterialVoucherController::class, 'authorizeVoucher'])
                ->name('material_vouchers.authorize');

            Route::patch('/vales-material/{materialVoucher}/status',
                [MaterialVoucherController::class, 'updateStatus'])
                ->name('material_vouchers.status.update');

            Route::get('/vales-material/{materialVoucher}/pdf',
                [MaterialVoucherController::class, 'downloadPdf'])
                ->name('material_vouchers.pdf');
        });

        // ── SOLCOM (Solicitudes de Compra) ────────────────────────────────────
        Route::middleware('role:admin|Solcom|Orden de compra')->group(function () {
            // Búsqueda de SOLCOM por número de folio (para modal OC — debe ir ANTES del resource)
            Route::get('/solicitudes-compra/buscar-por-folio',
                       [PurchaseRequestController::class, 'itemsJsonByFolio'])
                 ->name('purchase_requests.items_json_by_folio');

            Route::resource('/solicitudes-compra', PurchaseRequestController::class, [
                'names'      => [
                    'index'   => 'purchase_requests.index',
                    'create'  => 'purchase_requests.create',
                    'store'   => 'purchase_requests.store',
                    'show'    => 'purchase_requests.show',
                    'edit'    => 'purchase_requests.edit',
                    'update'  => 'purchase_requests.update',
                    'destroy' => 'purchase_requests.destroy',
                ],
                'parameters' => ['solicitudes-compra' => 'purchaseRequest'],
            ]);

            Route::post('/solicitudes-compra/{purchaseRequest}/items',
                        [PurchaseRequestController::class, 'storeItem'])
                 ->name('purchase_requests.items.store');

            Route::delete('/solicitudes-compra/{purchaseRequest}/items/{item}',
                          [PurchaseRequestController::class, 'destroyItem'])
                 ->name('purchase_requests.items.destroy');

            Route::patch('/solicitudes-compra/{purchaseRequest}/items/{item}',
                         [PurchaseRequestController::class, 'updateItem'])
                 ->name('purchase_requests.items.update');

            Route::post('/solicitudes-compra/{purchaseRequest}/notes',
                        [PurchaseRequestController::class, 'storeObservation'])
                 ->name('purchase_requests.notes.store');

              Route::patch('/solicitudes-compra/{purchaseRequest}/notes/{noteIndex}',
                        [PurchaseRequestController::class, 'updateObservation'])
                  ->name('purchase_requests.notes.update');

              Route::delete('/solicitudes-compra/{purchaseRequest}/notes/{noteIndex}',
                         [PurchaseRequestController::class, 'destroyObservation'])
                  ->name('purchase_requests.notes.destroy');

            // JSON de ítems para precarga en modal de creación de OC
            Route::get('/solicitudes-compra/{purchaseRequest}/items-json',
                       [PurchaseRequestController::class, 'itemsJson'])
                 ->name('purchase_requests.items_json');

            Route::get('/solicitudes-compra/{purchaseRequest}/pdf',
                       [PurchaseRequestController::class, 'downloadPdf'])
                 ->name('purchase_requests.pdf');

            // ── Flujo de trabajo SOLCOM ───────────────────────────────────────
            Route::post('/solicitudes-compra/{purchaseRequest}/send-to-purchasing',
                        [PurchaseRequestController::class, 'sendToPurchasing'])
                 ->name('purchase_requests.send_to_purchasing');

            Route::post('/solicitudes-compra/{purchaseRequest}/request-changes',
                        [PurchaseRequestController::class, 'requestChanges'])
                 ->name('purchase_requests.request_changes');

            Route::post('/solicitudes-compra/{purchaseRequest}/change-notes/{changeNote}/resolve',
                        [PurchaseRequestController::class, 'resolveChangeNote'])
                 ->name('purchase_requests.change_notes.resolve');

            // ── Pila SOLCOM (vista de Compras por usuario) ───────────────────
            Route::get('/compras/pila-solcom',
                       [PurchaseRequestController::class, 'purchasingPile'])
                 ->name('purchasing.solcom_pile');

        });

        // ── Carga de Trabajo SOLCOM (solo Orden de compra) ─────────────────
        Route::middleware('role:admin|Orden de compra')->group(function () {
            Route::get('/compras/carga-de-trabajo',
                       [PurchaseRequestController::class, 'workload'])
                 ->name('purchasing.workload');

        });

        // ── Almacén: Pila SOLMAT + crear SOLCOM desde SOLMAT ─────────────────
        Route::middleware('role:admin|Solmat|Orden de compra')->group(function () {
            Route::get('/solicitudes-material/{materialRequest}', [MaterialRequestController::class, 'show'])
                 ->name('material_requests.show');

            Route::get('/almacen/pila-solmat',
                       [PurchaseRequestController::class, 'solmatPile'])
                 ->name('warehouse.solmat_pile');

            Route::get('/almacen/pila-solmat/{materialRequest}/crear-solcom',
                       [PurchaseRequestController::class, 'createFromSolmat'])
                 ->name('purchase_requests.create_from_solmat');
        });
    });
});
