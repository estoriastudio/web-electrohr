<?php

/* Controladores */
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierContactController;
use App\Http\Controllers\SupplierLocationController;
use App\Http\Controllers\MobileAssetController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderMilestoneController;
use App\Http\Controllers\PurchaseOrderInvoiceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectWorkController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\ConceptController;

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
        Route::middleware('role:admin|orders')->group(function () {
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

            Route::resource('proveedores.sucursales', SupplierLocationController::class, [
                'names' => [
                    'store'   => 'supplier_locations.store',
                    'update'  => 'supplier_locations.update',
                    'destroy' => 'supplier_locations.destroy',
                ],
                'parameters' => ['proveedores' => 'supplier', 'sucursales' => 'location'],
            ])->only(['store', 'update', 'destroy']);
        });

        Route::get('bienes-mobiles/export', [MobileAssetController::class, 'export'])->name('mobile_assets.export');
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

        Route::get('/proyectos/{project}/obras-json', [ProjectController::class, 'worksJson'])
            ->name('projects.works_json');

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

        // Conceptos (catálogo) — búsqueda JSON accesible a admin|orders
        Route::middleware('role:admin|orders')->group(function () {
            Route::get('/conceptos/buscar', [ConceptController::class, 'search'])->name('concepts.search');
        });

        // Conceptos (catálogo) — CRUD solo admin
        Route::middleware('role:admin')->group(function () {
            Route::resource('/conceptos', ConceptController::class, [
                'names' => [
                    'index'   => 'concepts.index',
                    'store'   => 'concepts.store',
                    'update'  => 'concepts.update',
                    'destroy' => 'concepts.destroy',
                ],
                'parameters' => ['conceptos' => 'concept'],
            ])->only(['index', 'store', 'update', 'destroy']);
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

        // Órdenes de Compra — lectura: admin, payments, orders
        Route::middleware('role:admin|payments|orders')->group(function () {
            Route::get('/ordenes-de-compra', [PurchaseOrderController::class, 'index'])->name('purchase_orders.index');
            Route::get('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase_orders.show');
        });

        // Órdenes de Compra — escritura: admin, orders
        Route::middleware('role:admin|orders')->group(function () {
            Route::get('/ordenes-de-compra/create', [PurchaseOrderController::class, 'create'])->name('purchase_orders.create');
            Route::post('/ordenes-de-compra', [PurchaseOrderController::class, 'store'])->name('purchase_orders.store');
            Route::get('/ordenes-de-compra/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase_orders.edit');
            Route::put('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'update'])->name('purchase_orders.update');
            Route::patch('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'update']);
            Route::delete('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'destroy'])->name('purchase_orders.destroy');
        });

        // Hitos — lectura: admin, payments, orders
        Route::middleware('role:admin|payments|orders')->group(function () {
            Route::get('/hitos', [PurchaseOrderMilestoneController::class, 'index'])->name('milestones.index');
            Route::get('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'show'])->name('milestones.show');
        });

        // Hitos — escritura: admin, orders
        Route::middleware('role:admin|orders')->group(function () {
            Route::get('/hitos/create', [PurchaseOrderMilestoneController::class, 'create'])->name('milestones.create');
            Route::post('/hitos', [PurchaseOrderMilestoneController::class, 'store'])->name('milestones.store');
            Route::get('/hitos/{purchaseOrderMilestone}/edit', [PurchaseOrderMilestoneController::class, 'edit'])->name('milestones.edit');
            Route::put('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'update'])->name('milestones.update');
            Route::patch('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'update']);
            Route::delete('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'destroy'])->name('milestones.destroy');
        });

        // ── Admin + Payments ──────────────────────────────────────────────────

        // Pagos
        Route::middleware('role:admin|payments')->group(function () {
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
        Route::middleware('role:admin|payments')->group(function () {
            Route::post('/facturas', [PurchaseOrderInvoiceController::class, 'store'])->name('invoices.store');
            Route::get('/facturas/{invoice}/download', [PurchaseOrderInvoiceController::class, 'download'])->name('invoices.download');
            Route::delete('/facturas/{invoice}', [PurchaseOrderInvoiceController::class, 'destroy'])->name('invoices.destroy');
        });

        // ── SOLMAT (Solicitudes de Material) ──────────────────────────────────
        Route::middleware('role:admin|orders')->group(function () {
            // AJAX lookup (debe ir antes del resource para evitar conflicto con {material_request})
            Route::get('/solicitudes-material/buscar', [MaterialRequestController::class, 'jsonByFolio'])
                 ->name('material_requests.lookup');

            Route::resource('/solicitudes-material', MaterialRequestController::class, [
                'names'      => [
                    'index'   => 'material_requests.index',
                    'create'  => 'material_requests.create',
                    'store'   => 'material_requests.store',
                    'show'    => 'material_requests.show',
                    'edit'    => 'material_requests.edit',
                    'update'  => 'material_requests.update',
                    'destroy' => 'material_requests.destroy',
                ],
                'parameters' => ['solicitudes-material' => 'materialRequest'],
            ]);

            Route::post('/solicitudes-material/{materialRequest}/items',
                        [MaterialRequestController::class, 'storeItem'])
                 ->name('material_requests.items.store');

            Route::delete('/solicitudes-material/{materialRequest}/items/{item}',
                          [MaterialRequestController::class, 'destroyItem'])
                 ->name('material_requests.items.destroy');

            Route::post('/solicitudes-material/{materialRequest}/notes',
                        [MaterialRequestController::class, 'storeObservation'])
                 ->name('material_requests.notes.store');
        });

        // ── SOLCOM (Solicitudes de Compra) ────────────────────────────────────
        Route::middleware('role:admin|orders')->group(function () {
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

            Route::post('/solicitudes-compra/{purchaseRequest}/notes',
                        [PurchaseRequestController::class, 'storeObservation'])
                 ->name('purchase_requests.notes.store');
        });
    });
});
