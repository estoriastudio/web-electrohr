<?php

/* Controladores */
use App\Http\Controllers\UserController;
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

        // Perfil propio — accesible para todos los roles autenticados
        Route::get('/usuarios/{user}', [UserController::class, 'show'])->name('usuarios.show');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');

        // ── Solo admin ────────────────────────────────────────────────────────

        // Proveedores
        Route::middleware('role:admin')->group(function () {
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
    });
});
