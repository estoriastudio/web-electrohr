<?php

/* Controladores */
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderMilestoneController;
use App\Http\Controllers\PurchaseOrderInvoiceController;

use App\Http\Controllers\PaymentController;

/* Ayudantes */
use Illuminate\Support\Facades\Route;

/* Rutas protegidas por autenticación */
Route::namespace('App\Http\Controllers')->group(function () {
    Route::group(['middleware' => ['auth']], function () {
        Route::get('/', 'AdminController@dashboard')->name('dashboard');
        Route::get('/configuracion', 'AdminController@settings')->name('settings');

        // Supplier management
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
        
        // Órdenes de Compra
        Route::resource('/ordenes-de-compra', PurchaseOrderController::class, [
            'names' => [
                'index'   => 'purchase_orders.index',
                'create'  => 'purchase_orders.create',
                'store'   => 'purchase_orders.store',
                'show'    => 'purchase_orders.show',
                'edit'    => 'purchase_orders.edit',
                'update'  => 'purchase_orders.update',
                'destroy' => 'purchase_orders.destroy',
            ],
            'parameters' => ['ordenes-de-compra' => 'purchase_order'],
        ]);

        // Hitos de Ordenes de Compra
        Route::resource('/hitos', PurchaseOrderMilestoneController::class, [
            'names' => [
                'index'   => 'milestones.index',
                'create'  => 'milestones.create',
                'store'   => 'milestones.store',
                'show'    => 'milestones.show',
                'edit'    => 'milestones.edit',
                'update'  => 'milestones.update',
                'destroy' => 'milestones.destroy',
            ],
            'parameters' => ['hitos' => 'purchaseOrderMilestone'],
        ]);

        // Pagos
        Route::get('/pagos/autorizar', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/pagos/interactivo/autorizar', [PaymentController::class, 'interactive'])->name('payments.interactive');
        Route::post('/pagos/{payment}/swipe', [PaymentController::class, 'swipe'])->name('payments.swipe');
        Route::resource('/pagos', PaymentController::class)->except(['index'])->names([
            'create'  => 'payments.create',
            'store'   => 'payments.store',
            'show'    => 'payments.show',
            'edit'    => 'payments.edit',
            'update'  => 'payments.update',
            'destroy' => 'payments.destroy',
        ])->parameters(['pagos' => 'payment']);

        // Facturas de Órdenes de Compra
        Route::post('/facturas', [PurchaseOrderInvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/facturas/{invoice}/download', [PurchaseOrderInvoiceController::class, 'download'])->name('invoices.download');
        Route::delete('/facturas/{invoice}', [PurchaseOrderInvoiceController::class, 'destroy'])->name('invoices.destroy');

        // Usuarios
        Route::resource('usuarios', UserController::class, [
            'names' => [
                'index' => 'usuarios.index',
                'create' => 'usuarios.create',
                'store' => 'usuarios.store',
                'show' => 'usuarios.show',
                'edit' => 'usuarios.edit',
                'update' => 'usuarios.update',
                'destroy' => 'usuarios.destroy',
            ],
            'parameters' => ['usuarios' => 'user']
        ]);

        // Roles
        Route::post('/roles', 'UserController@storeRole')->name('roles.store');
        Route::delete('/roles/{role}', 'UserController@destroyRole')->name('roles.destroy');

        // Auditoría / Notificaciones
        Route::get('/auditoria', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/auditoria/marcar-leidas', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    });
});
