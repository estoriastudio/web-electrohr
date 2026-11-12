<?php

/* Controladores */
use App\Http\Controllers\ConceptCategoryController;
use App\Http\Controllers\ConceptController;
use App\Http\Controllers\DirectionInboxController;
use App\Http\Controllers\EngineerProjectController;
use App\Http\Controllers\EventFollowupController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\IncentiveController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\MaterialVoucherController;
use App\Http\Controllers\MobileAssetController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollCostReportController;
use App\Http\Controllers\PayrollFieldReportController;
use App\Http\Controllers\PayrollLineController;
use App\Http\Controllers\PayrollPeriodController;
use App\Http\Controllers\PieceworkWeeklyEntryController;
use App\Http\Controllers\PositionCategoryController;
use App\Http\Controllers\ProjectAgreementController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectEstimateController;
use App\Http\Controllers\ProjectEstimateFileController;
use App\Http\Controllers\ProjectEstimateInvoiceController;
use App\Http\Controllers\ProjectWorkController;
use App\Http\Controllers\PurchaseOrderChangeRequestController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderEvidenceController;
use App\Http\Controllers\PurchaseOrderInvoiceController;
use App\Http\Controllers\PurchaseOrderMilestoneController;
use App\Http\Controllers\PurchaseOrderMilestoneFulfillmentController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\SiteDeliveryController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockEntryController;
use App\Http\Controllers\StockExitController;
use App\Http\Controllers\SupplierAccountStatementController;
use App\Http\Controllers\SupplierContactController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierLocationController;
use App\Http\Controllers\SupplierPortalController;
use App\Http\Controllers\SupplierPortalInvoiceController;
use App\Http\Controllers\ToolCalibrationController;
use App\Http\Controllers\ToolCategoryController;
use App\Http\Controllers\ToolControlController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkerAttendanceController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\WorkerDc3Controller;
use App\Http\Controllers\WorkerFileController;
use App\Http\Controllers\WorkerGroupController;
use App\Http\Controllers\WorkerTerminationController;
use App\Http\Controllers\WorkerVacationController;
/* Ayudantes */
use Illuminate\Support\Facades\Route;

/* Rutas protegidas por autenticación */
Route::namespace('App\Http\Controllers')->group(function () {
    Route::group(['middleware' => ['auth']], function () {
        Route::get('/', 'AdminController@dashboard')->name('dashboard');
        Route::get('/configuracion', 'AdminController@settings')->name('settings');
        Route::get('/buscar', GlobalSearchController::class)->name('global_search');

        // Simulación de accesos del admin (ACCESS_SIMULATION_ENABLED); el controlador valida quién la usa.
        Route::post('/simulacion-acceso', [\App\Http\Controllers\AccessSimulationController::class, 'start'])->name('access_simulation.start');
        Route::delete('/simulacion-acceso', [\App\Http\Controllers\AccessSimulationController::class, 'stop'])->name('access_simulation.stop');

        // Perfil propio — accesible para todos los roles autenticados
        Route::get('/usuarios/{user}', [UserController::class, 'show'])->name('usuarios.show');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');

        // Portal de proveedores
        Route::middleware(['role:supplier_portal_access', 'supplier_portal'])
            ->prefix('portal-proveedor')
            ->name('supplier_portal.')
            ->group(function () {
                Route::get('/', [SupplierPortalController::class, 'dashboard'])->name('dashboard');
                Route::get('/ordenes-compra', [SupplierPortalController::class, 'purchaseOrders'])->name('purchase_orders.index');
                Route::get('/ordenes-compra/{purchaseOrder}/preview', [SupplierPortalController::class, 'purchaseOrderPreview'])
                    ->whereNumber('purchaseOrder')
                    ->name('purchase_orders.preview');
                Route::get('/vales-material', [SupplierPortalController::class, 'materialVouchers'])->name('material_vouchers.index');
                Route::get('/estado-cuenta', [SupplierPortalController::class, 'accountStatement'])->name('account_statement.index');
                Route::get('/instructivo-carga-facturas', [SupplierPortalController::class, 'downloadInvoiceGuide'])
                    ->name('invoice_guide.download');
                Route::get('/ordenes-compra/{purchaseOrder}/facturas/nueva', [SupplierPortalInvoiceController::class, 'create'])
                    ->name('invoices.create');
                Route::post('/ordenes-compra/{purchaseOrder}/facturas', [SupplierPortalInvoiceController::class, 'store'])
                    ->name('invoices.store');
                Route::get('/ordenes-compra/{purchaseOrder}/facturas/{invoice}/nota-credito/editar', [SupplierPortalInvoiceController::class, 'editCreditNote'])
                    ->whereNumber('purchaseOrder')
                    ->whereNumber('invoice')
                    ->name('invoices.credit_note.edit');
                Route::patch('/ordenes-compra/{purchaseOrder}/facturas/{invoice}/nota-credito', [SupplierPortalInvoiceController::class, 'updateCreditNote'])
                    ->whereNumber('purchaseOrder')
                    ->whereNumber('invoice')
                    ->name('invoices.credit_note.update');
                Route::get('/ordenes-compra/{purchaseOrder}/facturas/{invoice}/archivo/{type}', [SupplierPortalInvoiceController::class, 'downloadFile'])
                    ->whereNumber('purchaseOrder')
                    ->whereNumber('invoice')
                    ->whereIn('type', ['pdf', 'xml', 'evidence', 'credit_note_pdf', 'credit_note_xml'])
                    ->name('invoices.download_file');
            });

        // Vistas de detalle compartidas (solo lectura) para todo el equipo autenticado
        Route::get('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'show'])
            ->whereNumber('purchase_order')
            ->middleware('permission:purchase_orders.view')
            ->name('purchase_orders.show');
        Route::get('/solicitudes-compra/{purchaseRequest}', [PurchaseRequestController::class, 'show'])
            ->whereNumber('purchaseRequest')
            ->middleware('permission:purchase_requests.view')
            ->name('purchase_requests.show');
        Route::get('/solicitudes-material/{materialRequest}', [MaterialRequestController::class, 'show'])
            ->whereNumber('materialRequest')
            ->middleware('permission:material_requests.view')
            ->name('material_requests.show');
        Route::get('/ordenes-de-compra/{purchase_order}/pdf', [PurchaseOrderController::class, 'downloadPdf'])
            ->whereNumber('purchase_order')
            ->middleware('permission:purchase_orders.download')
            ->name('purchase_orders.pdf');
        Route::get('/solicitudes-compra/{purchaseRequest}/pdf', [PurchaseRequestController::class, 'downloadPdf'])
            ->whereNumber('purchaseRequest')
            ->middleware('permission:purchase_requests.view')
            ->name('purchase_requests.pdf');
        Route::get('/solicitudes-material/{materialRequest}/pdf', [MaterialRequestController::class, 'downloadPdf'])
            ->whereNumber('materialRequest')
            ->middleware('permission:material_requests.view')
            ->name('material_requests.pdf');

        // ── Solo admin ────────────────────────────────────────────────────────

        // Proveedores: permisos de la matriz (vistas `suppliers` y `supplier_account_statements`). Compras da de
        // alta y edita; Dirección, Almacén y Pagos consultan. Exportar es D: solo admin (decisión 2026-11-11).
        Route::prefix('proveedores/estado-cuenta')->name('suppliers.account_statement.')->group(function () {
            Route::get('/', [SupplierAccountStatementController::class, 'index'])
                ->middleware('permission:supplier_account_statements.view')->name('index');
            Route::middleware('permission:supplier_account_statements.download')->group(function () {
                Route::get('/export', [SupplierAccountStatementController::class, 'export'])->name('export');
                Route::get('/exportar-documentos', [SupplierAccountStatementController::class, 'exportDocuments'])->name('export_documents');
                Route::get('/exportar-documentos/{export}/faltantes', [SupplierAccountStatementController::class, 'documentExportReport'])
                    ->whereUuid('export')->name('export_documents.report');
            });
            Route::get('/ordenes/{purchaseOrder}', [SupplierAccountStatementController::class, 'purchaseOrderSummary'])
                ->middleware('permission:supplier_account_statements.view')
                ->whereNumber('purchaseOrder')->name('order');
        });

        Route::middleware('permission:suppliers.view')->group(function () {
            Route::get('/proveedores/{supplier}', [SupplierController::class, 'show'])
                ->whereNumber('supplier')
                ->name('suppliers.show');
        });

        Route::get('proveedores/export', [SupplierController::class, 'export'])
            ->middleware('permission:suppliers.download')->name('suppliers.export');
        Route::middleware('permission:suppliers.create')->group(function () {
            Route::post('proveedores/import', [SupplierController::class, 'import'])->name('suppliers.import');
            Route::get('/proveedores/create', [SupplierController::class, 'create'])->name('suppliers.create');
            Route::post('/proveedores', [SupplierController::class, 'store'])->name('suppliers.store');
        });
        Route::middleware('permission:suppliers.view')->group(function () {
            Route::get('/proveedores', [SupplierController::class, 'index'])->name('suppliers.index');
            // Los usan los formularios de OC.
            Route::get('proveedores/{supplier}/contacto-principal', [SupplierContactController::class, 'primaryJson'])
                ->name('supplier_contacts.primary_json');
            Route::get('proveedores/{supplier}/estado-para-oc', [SupplierController::class, 'purchaseOrderReadiness'])
                ->name('suppliers.purchase_order_readiness');
        });
        // Editar, borrar, contactos, sucursales y acceso al portal: E (Compras, admin); borrar usa editar.
        Route::middleware('permission:suppliers.update')->group(function () {
            Route::get('/proveedores/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
            Route::match(['put', 'patch'], '/proveedores/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('/proveedores/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
            Route::put('proveedores/{supplier}/informacion', [SupplierController::class, 'updateInfo'])->name('suppliers.update_info');

            Route::resource('proveedores.contactos', SupplierContactController::class, [
                'names' => [
                    'store' => 'supplier_contacts.store',
                    'update' => 'supplier_contacts.update',
                    'destroy' => 'supplier_contacts.destroy',
                ],
                'parameters' => ['proveedores' => 'supplier', 'contactos' => 'contact'],
            ])->only(['store', 'update', 'destroy']);

            Route::resource('proveedores.sucursales', SupplierLocationController::class, [
                'names' => [
                    'store' => 'supplier_locations.store',
                    'update' => 'supplier_locations.update',
                    'destroy' => 'supplier_locations.destroy',
                ],
                'parameters' => ['proveedores' => 'supplier', 'sucursales' => 'location'],
            ])->only(['store', 'update', 'destroy']);

            Route::post('proveedores/{supplier}/portal-access/enable', [SupplierController::class, 'enablePortalAccess'])
                ->name('suppliers.portal_access.enable');
            Route::post('proveedores/{supplier}/portal-access/disable', [SupplierController::class, 'disablePortalAccess'])
                ->name('suppliers.portal_access.disable');
            Route::post('proveedores/{supplier}/portal-access/reactivate', [SupplierController::class, 'reactivatePortalAccess'])
                ->name('suppliers.portal_access.reactivate');
        });

        // Bienes Móviles (vista `mobile_assets`): Móviles da de alta y edita; Gerente y Dirección consultan.
        // Exportar y descargar el ZIP de documentos es D: solo admin. Bitácora, fotos y documentos usan editar.
        Route::get('bienes-mobiles/export', [MobileAssetController::class, 'export'])
            ->middleware('permission:mobile_assets.download')->name('mobile_assets.export');
        Route::middleware('permission:mobile_assets.create')->group(function () {
            Route::post('bienes-mobiles/import', [MobileAssetController::class, 'import'])->name('mobile_assets.import');
            Route::get('/bienes-mobiles/create', [MobileAssetController::class, 'create'])->name('mobile_assets.create');
            Route::post('/bienes-mobiles', [MobileAssetController::class, 'store'])->name('mobile_assets.store');
        });
        Route::middleware('permission:mobile_assets.view')->group(function () {
            Route::get('/bienes-mobiles', [MobileAssetController::class, 'index'])->name('mobile_assets.index');
            Route::get('/bienes-mobiles/{mobile_asset}', [MobileAssetController::class, 'show'])->name('mobile_assets.show');
        });
        Route::middleware('permission:mobile_assets.update')->group(function () {
            Route::get('/bienes-mobiles/{mobile_asset}/edit', [MobileAssetController::class, 'edit'])->name('mobile_assets.edit');
            Route::match(['put', 'patch'], '/bienes-mobiles/{mobile_asset}', [MobileAssetController::class, 'update'])->name('mobile_assets.update');
            Route::delete('/bienes-mobiles/{mobile_asset}', [MobileAssetController::class, 'destroy'])->name('mobile_assets.destroy');

            // Fotos del bien móvil (upload individual por slot)
            Route::post('bienes-mobiles/{mobile_asset}/foto/{slot}', [MobileAssetController::class, 'uploadPhoto'])
                ->name('mobile_assets.photo.upload')
                ->where('slot', '[123]');
            Route::delete('bienes-mobiles/{mobile_asset}/foto/{slot}', [MobileAssetController::class, 'deletePhoto'])
                ->name('mobile_assets.photo.delete')
                ->where('slot', '[123]');

            // Checklist documental y bitácora de mantenimiento
            Route::patch('bienes-mobiles/{mobile_asset}/documentos/{docType}', [MobileAssetController::class, 'updateDocument'])
                ->name('mobile_assets.document.update');
            Route::post('bienes-mobiles/{mobile_asset}/bitacora', [MaintenanceLogController::class, 'store'])
                ->name('maintenance_logs.store');
            Route::delete('bienes-mobiles/{mobile_asset}/bitacora/{maintenanceLog}', [MaintenanceLogController::class, 'destroy'])
                ->name('maintenance_logs.destroy');
        });
        Route::get('bienes-mobiles/{mobile_asset}/documentos/zip', [MobileAssetController::class, 'downloadDocumentsZip'])
            ->middleware('permission:mobile_assets.download')->name('mobile_assets.documents.zip');

        // Proyectos
        Route::get('/proyectos/buscar', [ProjectController::class, 'search'])->name('projects.search');
        Route::get('/proyectos/{project}/obras-json', [ProjectController::class, 'worksJson'])->name('projects.works_json');
        Route::get('/proyectos/{project}/obras/buscar', [ProjectController::class, 'searchWorks'])->name('projects.works.search');

        // Mis Proyectos (Gerente y Supervisor): solo consulta de los proyectos asignados al usuario.
        Route::middleware('permission:projects.view')->prefix('mis-proyectos')->name('my-projects.')->group(function () {
            Route::get('/', [EngineerProjectController::class, 'index'])->name('index');
            Route::get('/{project}', [EngineerProjectController::class, 'show'])->whereNumber('project')->name('show');
        });

        // Proyectos y obras: cada ruta pide el permiso de la matriz (vista `projects`). Los controladores
        // filtran por alcance a quien solo ve sus proyectos. Documentos, convenios y archivos de
        // estimaciones son de quien edita (decisión 2026-11-10); borrar usa el permiso de editar.
        Route::middleware('permission:projects.view')->group(function () {
            Route::get('/proyectos', [ProjectController::class, 'index'])->name('projects.index');
            Route::get('/proyectos/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
            Route::get('/obras', [ProjectWorkController::class, 'index'])->name('project_works.index');
            Route::get('/obras/{project_work}', [ProjectWorkController::class, 'show'])->whereNumber('project_work')->name('project_works.show');
        });

        Route::middleware('permission:projects.create')->group(function () {
            Route::get('/proyectos/create', [ProjectController::class, 'create'])->name('projects.create');
            Route::post('/proyectos', [ProjectController::class, 'store'])->name('projects.store');
            Route::post('/proyectos/import', [ProjectController::class, 'import'])->name('projects.import');
            Route::get('/obras/create', [ProjectWorkController::class, 'create'])->name('project_works.create');
            Route::post('/obras', [ProjectWorkController::class, 'store'])->name('project_works.store');
            Route::get('/proyectos/{project}/estimaciones/nueva', [ProjectEstimateController::class, 'create'])
                ->name('projects.estimates.create');
            Route::post('/proyectos/{project}/estimaciones', [ProjectEstimateController::class, 'store'])
                ->name('projects.estimates.store');
        });

        Route::middleware('permission:projects.update')->group(function () {
            Route::get('/proyectos/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
            Route::match(['put', 'patch'], '/proyectos/{project}', [ProjectController::class, 'update'])->name('projects.update');
            Route::delete('/proyectos/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
            Route::get('/obras/{project_work}/edit', [ProjectWorkController::class, 'edit'])->name('project_works.edit');
            Route::match(['put', 'patch'], '/obras/{project_work}', [ProjectWorkController::class, 'update'])->name('project_works.update');
            Route::delete('/obras/{project_work}', [ProjectWorkController::class, 'destroy'])->name('project_works.destroy');

            Route::post('/proyectos/{project}/documentos/{docType}', [ProjectController::class, 'uploadDocument'])
                ->name('projects.document.upload');
            Route::patch('/proyectos/{project}/documentos/{docType}/aplicabilidad', [ProjectController::class, 'updateDocumentApplicability'])
                ->name('projects.document.applicability');
            Route::post('/proyectos/{project}/documentos/{docType}/chunk/init', [ProjectController::class, 'initChunkUpload'])
                ->name('projects.document.chunk.init');
            Route::post('/proyectos/{project}/documentos/{docType}/chunk/upload', [ProjectController::class, 'uploadChunk'])
                ->name('projects.document.chunk.upload');
            Route::post('/proyectos/{project}/documentos/{docType}/chunk/finalize', [ProjectController::class, 'finalizeChunkUpload'])
                ->name('projects.document.chunk.finalize');
            Route::post('/proyectos/{project}/documentos/{docType}/chunk/abort', [ProjectController::class, 'abortChunkUpload'])
                ->name('projects.document.chunk.abort');

            Route::post('/proyectos/{project}/convenios', [ProjectAgreementController::class, 'store'])
                ->name('projects.agreements.store');
            Route::post('/proyectos/{project}/convenios/chunk/init', [ProjectAgreementController::class, 'initChunkUpload'])
                ->name('projects.agreements.chunk.init');
            Route::post('/proyectos/{project}/convenios/chunk/upload', [ProjectAgreementController::class, 'uploadChunk'])
                ->name('projects.agreements.chunk.upload');
            Route::post('/proyectos/{project}/convenios/chunk/finalize', [ProjectAgreementController::class, 'finalizeChunkUpload'])
                ->name('projects.agreements.chunk.finalize');
            Route::post('/proyectos/{project}/convenios/chunk/abort', [ProjectAgreementController::class, 'abortChunkUpload'])
                ->name('projects.agreements.chunk.abort');
            Route::get('/proyectos/{project}/convenios/{projectAgreement}/nombramientos', [ProjectAgreementController::class, 'downloadAppointments'])
                ->name('projects.agreements.appointments.download');

            Route::put('/proyectos/estimaciones/{estimate}', [ProjectEstimateController::class, 'update'])
                ->name('projects.estimates.update');
            Route::delete('/proyectos/estimaciones/{estimate}', [ProjectEstimateController::class, 'destroy'])
                ->name('projects.estimates.destroy');
            Route::post('/proyectos/estimaciones/{estimate}/facturas', [ProjectEstimateInvoiceController::class, 'store'])
                ->whereNumber('estimate')->name('projects.estimates.invoices.store');
            Route::get('/proyectos/estimaciones/facturas/{invoice}/{format}', [ProjectEstimateInvoiceController::class, 'download'])
                ->whereNumber('invoice')->whereIn('format', ['pdf', 'xml'])->name('projects.estimates.invoices.download');
            Route::delete('/proyectos/estimaciones/facturas/{invoice}', [ProjectEstimateInvoiceController::class, 'destroy'])
                ->whereNumber('invoice')->name('projects.estimates.invoices.destroy');
            Route::post('/proyectos/estimaciones/{estimate}/archivos', [ProjectEstimateFileController::class, 'store'])
                ->whereNumber('estimate')->name('projects.estimates.files.store');
            Route::get('/proyectos/estimaciones/archivos/{estimateFile}', [ProjectEstimateFileController::class, 'download'])
                ->whereNumber('estimateFile')->name('projects.estimates.files.download');
            Route::delete('/proyectos/estimaciones/archivos/{estimateFile}', [ProjectEstimateFileController::class, 'destroy'])
                ->whereNumber('estimateFile')->name('projects.estimates.files.destroy');
        });

        // Recursos Humanos: cada ruta pide el permiso de la matriz de acceso
        // (database/data/access_matrix.php). El alcance por obra lo aplican los controladores.
        // Las acciones de borrar/cancelar sin equivalente en la matriz usan el permiso de editar.
        Route::prefix('human_resources')
            ->name('human_resources.')
            ->group(function () {
                // Trabajadores
                Route::get('workers/export', [WorkerController::class, 'export'])->name('workers.export')->middleware('permission:hr_workers.view');
                Route::post('workers/import-payroll', [WorkerController::class, 'importPayroll'])->name('workers.import-payroll')->middleware('permission:hr_workers.create');
                Route::post('workers/import-terminations', [WorkerController::class, 'importTerminations'])->name('workers.import-terminations')->middleware('permission:hr_terminations.create');
                Route::resource('workers', WorkerController::class)->only(['create', 'store'])->middleware('permission:hr_workers.create');
                Route::resource('workers', WorkerController::class)->only(['index', 'show'])->middleware('permission:hr_workers.view');
                Route::resource('workers', WorkerController::class)->only(['edit', 'update', 'destroy'])->middleware('permission:hr_workers.update');
                Route::middleware('permission:hr_workers.view')->group(function () {
                    Route::get('workers/{worker}/profile-photo', [WorkerController::class, 'profilePhoto'])->name('workers.profile-photo');
                    Route::get('workers/{worker}/file', [WorkerFileController::class, 'show'])->name('workers.file.show');
                    Route::get('workers/{worker}/file/{document}/download', [WorkerFileController::class, 'download'])->name('workers.file.download');
                    Route::get('workers/{worker}/file/dc3/{dc3}/download', [WorkerDc3Controller::class, 'download'])->name('workers.file.dc3.download');
                });
                Route::middleware('permission:hr_workers.update')->group(function () {
                    Route::post('workers/{worker}/pre-register', [WorkerController::class, 'preRegister'])->name('workers.pre-register');
                    Route::post('workers/{worker}/activate', [WorkerController::class, 'activate'])->name('workers.activate');
                    Route::post('workers/{worker}/reactivate', [WorkerController::class, 'reactivate'])->name('workers.reactivate');
                    Route::get('workers/{worker}/file/edit', [WorkerFileController::class, 'edit'])->name('workers.file.edit');
                    Route::put('workers/{worker}/file', [WorkerFileController::class, 'update'])->name('workers.file.update');
                    Route::post('workers/{worker}/file/dc3', [WorkerDc3Controller::class, 'store'])->name('workers.file.dc3.store');
                    Route::delete('workers/{worker}/file/dc3/{dc3}', [WorkerDc3Controller::class, 'destroy'])->name('workers.file.dc3.destroy');
                });
                Route::get('workers/{worker}/payroll-lines/{payrollLine}/receipt', [WorkerController::class, 'downloadPayrollReceipt'])->name('workers.payroll-receipt.download')->middleware('permission:hr_payroll.view');

                // Bajas
                Route::post('workers/{worker}/terminate', [WorkerTerminationController::class, 'store'])->name('workers.terminate')->middleware('permission:hr_terminations.create');
                Route::middleware('permission:hr_terminations.view')->group(function () {
                    Route::get('worker-terminations', [WorkerTerminationController::class, 'index'])->name('worker-terminations.index');
                    Route::get('worker-terminations/{workerTermination}', [WorkerTerminationController::class, 'show'])->name('worker-terminations.show');
                });

                // Cuadrillas
                Route::resource('worker-groups', WorkerGroupController::class)->only(['create', 'store'])->middleware('permission:hr_worker_groups.create');
                Route::resource('worker-groups', WorkerGroupController::class)->only(['index', 'show'])->middleware('permission:hr_worker_groups.view');
                Route::resource('worker-groups', WorkerGroupController::class)->only(['edit', 'update', 'destroy'])->middleware('permission:hr_worker_groups.update');
                Route::middleware('permission:hr_worker_groups.update')->group(function () {
                    Route::post('worker-groups/{workerGroup}/relocate', [WorkerGroupController::class, 'relocate'])->name('worker-groups.relocate');
                    Route::get('worker-groups/{workerGroup}/members/search', [WorkerGroupController::class, 'searchEligibleMembers'])->name('worker-groups.members.search');
                    Route::post('worker-groups/{workerGroup}/members', [WorkerGroupController::class, 'addMember'])->name('worker-groups.members.add');
                    Route::delete('worker-groups/{workerGroup}/members/{worker}', [WorkerGroupController::class, 'removeMember'])->name('worker-groups.members.remove');
                });

                // Asistencias
                Route::middleware('permission:hr_attendances.view')->group(function () {
                    Route::get('worker-attendances/export', [WorkerAttendanceController::class, 'export'])->name('worker-attendances.export');
                    Route::get('worker-groups/{workerGroup}/attendance', [WorkerAttendanceController::class, 'groupAttendance'])->name('worker-groups.attendance');
                    Route::get('worker-attendances/{workerAttendance}/absence-document', [WorkerAttendanceController::class, 'downloadAbsenceDocument'])->name('worker-attendances.absence-document');
                });
                Route::post('worker-groups/{workerGroup}/attendance/{worker}', [WorkerAttendanceController::class, 'markGroupAttendance'])->name('worker-groups.attendance.mark')->middleware('permission:hr_attendances.create|hr_attendances.update');
                Route::resource('worker-attendances', WorkerAttendanceController::class)->only(['create', 'store'])->middleware('permission:hr_attendances.create|hr_attendances.update');
                Route::resource('worker-attendances', WorkerAttendanceController::class)->only(['index', 'show'])->middleware('permission:hr_attendances.view');
                Route::resource('worker-attendances', WorkerAttendanceController::class)->only(['edit', 'update', 'destroy'])->middleware('permission:hr_attendances.update');

                // Incentivos
                Route::post('worker-groups/{workerGroup}/attendance/{worker}/incentives', [WorkerAttendanceController::class, 'storeGroupIncentive'])->name('worker-groups.attendance.incentives.store')->middleware('permission:hr_incentives.create');
                Route::resource('incentives', IncentiveController::class)->only(['create', 'store'])->middleware('permission:hr_incentives.create');
                Route::resource('incentives', IncentiveController::class)->only(['index', 'show'])->middleware('permission:hr_incentives.view');
                Route::resource('incentives', IncentiveController::class)->only(['edit', 'update', 'destroy'])->middleware('permission:hr_incentives.update');

                // Vacaciones
                Route::resource('worker-vacations', WorkerVacationController::class)->only(['create', 'store'])->middleware('permission:hr_vacations.create');
                Route::resource('worker-vacations', WorkerVacationController::class)->only(['index', 'show'])->middleware('permission:hr_vacations.view');
                Route::resource('worker-vacations', WorkerVacationController::class)->only(['edit', 'update', 'destroy'])->middleware('permission:hr_vacations.update');
                Route::post('worker-vacations/{workerVacation}/approve', [WorkerVacationController::class, 'approve'])->name('worker-vacations.approve')->middleware('permission:hr_vacations.authorize');
                Route::post('worker-vacations/{workerVacation}/cancel', [WorkerVacationController::class, 'cancel'])->name('worker-vacations.cancel')->middleware('permission:hr_vacations.update');

                // Categorías de puesto
                Route::resource('position-categories', PositionCategoryController::class)->only(['create', 'store'])->middleware('permission:hr_position_categories.create');
                Route::resource('position-categories', PositionCategoryController::class)->only(['index', 'show'])->middleware('permission:hr_position_categories.view');
                Route::resource('position-categories', PositionCategoryController::class)->only(['edit', 'update', 'destroy'])->middleware('permission:hr_position_categories.update');

                // Nómina
                Route::middleware('permission:hr_payroll.view')->group(function () {
                    Route::get('payroll-periods', [PayrollPeriodController::class, 'index'])->name('payroll-periods.index');
                    Route::resource('payroll-lines', PayrollLineController::class)->only(['index', 'show']);
                    Route::get('payroll/cost-by-project', [PayrollCostReportController::class, 'byProjectWork'])->name('payroll.cost-by-project');
                    Route::get('payroll/cost-by-worker-group', [PayrollCostReportController::class, 'byWorkerGroup'])->name('payroll.cost-by-worker-group');
                    Route::get('payroll/field-report', [PayrollFieldReportController::class, 'index'])->name('payroll.field-report');
                });
                Route::middleware('permission:hr_payroll.create')->group(function () {
                    Route::post('payroll-periods', [PayrollPeriodController::class, 'store'])->name('payroll-periods.store');
                    Route::post('payroll-periods/{payrollPeriod}/generate-lines', [PayrollLineController::class, 'generate'])->name('payroll-periods.generate-lines');
                });
                Route::middleware('permission:hr_payroll.update')->group(function () {
                    Route::resource('payroll-periods', PayrollPeriodController::class)->only(['update', 'destroy']);
                    Route::post('payroll-periods/{payrollPeriod}/close', [PayrollPeriodController::class, 'close'])->name('payroll-periods.close');
                    Route::post('payroll-periods/{payrollPeriod}/mark-paid', [PayrollPeriodController::class, 'markPaid'])->name('payroll-periods.mark-paid');
                });
                // Fase 4 (V-02): Dirección autoriza la nómina cerrada.
                Route::middleware('permission:hr_payroll.authorize')->group(function () {
                    Route::post('payroll-periods/{payrollPeriod}/authorize', [PayrollPeriodController::class, 'authorizePayroll'])->name('payroll-periods.authorize');
                    Route::resource('payroll-lines', PayrollLineController::class)->only(['update']);
                    Route::post('payroll-lines/{payrollLine}/rebuild', [PayrollLineController::class, 'rebuild'])->name('payroll-lines.rebuild');
                });

                // Destajo
                Route::resource('piecework-entries', PieceworkWeeklyEntryController::class)->only(['store'])->middleware('permission:hr_piecework.create');
                Route::resource('piecework-entries', PieceworkWeeklyEntryController::class)->only(['index', 'show'])->middleware('permission:hr_piecework.view');
                Route::resource('piecework-entries', PieceworkWeeklyEntryController::class)->only(['update', 'destroy'])->middleware('permission:hr_piecework.update');
            });

        // Conceptos (catálogo) — búsqueda JSON accesible a admin|Orden de compra
        Route::get('/conceptos/buscar', [ConceptController::class, 'search'])->name('concepts.search');
        Route::get('/conceptos/codigo-siguiente', [ConceptController::class, 'nextCode'])->name('concepts.next_code');
        // Precios adjudicados (`awarded_prices`): Dirección y Compras. Antes lo abría cualquier usuario.
        Route::get('/conceptos/precios-adjudicados', [ConceptController::class, 'awardedPrices'])
            ->middleware('permission:awarded_prices.view')->name('concepts.awarded_prices');
        Route::get('/categorias-conceptos/{conceptCategory}/subcategorias-json', [ConceptCategoryController::class, 'subcategoriesJson'])->name('concept_categories.subcategories_json');
        Route::get('/categorias-herramientas/{toolCategory}/subcategorias-json', [ToolCategoryController::class, 'subcategoriesJson'])->name('tool_categories.subcategories_json');

        // Conceptos (`concepts`) y Familias (`concept_categories`): Almacén mantiene el catálogo; Compras consulta.
        // Archivar y borrar usan editar. La búsqueda JSON de arriba la usan los formularios de todos los módulos.
        Route::middleware('permission:concepts.view')->group(function () {
            Route::get('/conceptos/archivados', [ConceptController::class, 'archived'])->name('concepts.archived');
            Route::get('/conceptos', [ConceptController::class, 'index'])->name('concepts.index');
        });
        Route::middleware('permission:concepts.create')->group(function () {
            Route::post('/conceptos/import', [ConceptController::class, 'import'])->name('concepts.import');
            Route::post('/conceptos', [ConceptController::class, 'store'])->name('concepts.store');
        });
        Route::middleware('permission:concepts.update')->group(function () {
            Route::patch('/conceptos/{concept}/archivar', [ConceptController::class, 'archive'])->name('concepts.archive');
            Route::patch('/conceptos/{concept}/desarchivar', [ConceptController::class, 'unarchive'])->name('concepts.unarchive');
            Route::match(['put', 'patch'], '/conceptos/{concept}', [ConceptController::class, 'update'])->name('concepts.update');
        });

        Route::get('/categorias-conceptos', [ConceptCategoryController::class, 'index'])
            ->middleware('permission:concept_categories.view')->name('concept_categories.index');
        Route::middleware('permission:concept_categories.create')->group(function () {
            Route::post('/categorias-conceptos', [ConceptCategoryController::class, 'store'])->name('concept_categories.store');
            Route::post('/categorias-conceptos/{conceptCategory}/subcategorias', [ConceptCategoryController::class, 'storeSubcategory'])->name('concept_categories.subcategories.store');
        });
        Route::middleware('permission:concept_categories.update')->group(function () {
            Route::put('/categorias-conceptos/{conceptCategory}', [ConceptCategoryController::class, 'update'])->name('concept_categories.update');
            Route::delete('/categorias-conceptos/{conceptCategory}', [ConceptCategoryController::class, 'destroy'])->name('concept_categories.destroy');
            Route::put('/categorias-conceptos/{conceptCategory}/compradores', [ConceptCategoryController::class, 'syncUsers'])->name('concept_categories.sync_users');
            Route::put('/categorias-conceptos/{conceptCategory}/subcategorias/{subcategory}', [ConceptCategoryController::class, 'updateSubcategory'])->name('concept_categories.subcategories.update');
            Route::delete('/categorias-conceptos/{conceptCategory}/subcategorias/{subcategory}', [ConceptCategoryController::class, 'destroySubcategory'])->name('concept_categories.subcategories.destroy');
        });

        // Herramientas: Registro (`tools`: Almacén y Móviles dan de alta y editan; Gerente y Supervisor
        // consultan), Control de uso (`tool_controls`: Almacén registra) y Categorías (`tool_categories`: Almacén).
        Route::middleware('permission:tool_categories.view')->group(function () {
            Route::get('/categorias-herramientas', [ToolCategoryController::class, 'index'])->name('tool_categories.index');
        });
        Route::post('/categorias-herramientas', [ToolCategoryController::class, 'store'])
            ->middleware('permission:tool_categories.create')->name('tool_categories.store');
        Route::middleware('permission:tool_categories.update')->group(function () {
            Route::match(['put', 'patch'], '/categorias-herramientas/{toolCategory}', [ToolCategoryController::class, 'update'])->name('tool_categories.update');
            Route::delete('/categorias-herramientas/{toolCategory}', [ToolCategoryController::class, 'destroy'])->name('tool_categories.destroy');
        });

        Route::get('/herramientas/controles', [ToolControlController::class, 'index'])
            ->middleware('permission:tool_controls.view')->name('tool_controls.index');
        Route::middleware('permission:tool_controls.register')->group(function () {
            Route::post('/herramientas/controles', [ToolControlController::class, 'store'])->name('tool_controls.store');
            Route::put('/herramientas/controles/{toolControl}', [ToolControlController::class, 'update'])->name('tool_controls.update');
            Route::delete('/herramientas/controles/{toolControl}', [ToolControlController::class, 'destroy'])->name('tool_controls.destroy');
        });

        Route::middleware('permission:tools.update')->group(function () {
            Route::post('/herramientas/{tool}/foto/{slot}', [ToolController::class, 'uploadPhoto'])
                ->name('tools.photo.upload')
                ->where('slot', '[123]');
            Route::delete('/herramientas/{tool}/foto/{slot}', [ToolController::class, 'deletePhoto'])
                ->name('tools.photo.delete')
                ->where('slot', '[123]');
            Route::post('/herramientas/{tool}/calibraciones', [ToolCalibrationController::class, 'store'])
                ->name('tool_calibrations.store');
            Route::delete('/herramientas/{tool}/calibraciones/{toolCalibration}', [ToolCalibrationController::class, 'destroy'])
                ->name('tool_calibrations.destroy');
        });

        Route::get('/herramientas/archivadas', [ToolController::class, 'archived'])
            ->middleware('permission:tools.view')->name('tools.archived');
        Route::middleware('permission:tools.update')->group(function () {
            Route::patch('/herramientas/{tool}/archivar', [ToolController::class, 'archive'])->name('tools.archive');
            Route::patch('/herramientas/{tool}/desarchivar', [ToolController::class, 'unarchive'])->name('tools.unarchive');
        });
        Route::delete('/herramientas/{tool}/eliminar-permanente', [ToolController::class, 'forceDestroy'])
            ->middleware('role:admin')
            ->name('tools.force_destroy');

        Route::middleware('permission:tools.view')->group(function () {
            Route::get('/herramientas', [ToolController::class, 'index'])->name('tools.index');
            Route::get('/herramientas/{tool}', [ToolController::class, 'show'])->name('tools.show');
        });
        Route::post('/herramientas', [ToolController::class, 'store'])
            ->middleware('permission:tools.create')->name('tools.store');
        Route::match(['put', 'patch'], '/herramientas/{tool}', [ToolController::class, 'update'])
            ->middleware('permission:tools.update')->name('tools.update');

        // Usuarios (gestión) y Roles: vista `users`, solo admin.
        Route::middleware('permission:users.view|users.create|users.update')->group(function () {
            Route::resource('usuarios', UserController::class, [
                'names' => [
                    'index' => 'usuarios.index',
                    'create' => 'usuarios.create',
                    'store' => 'usuarios.store',
                    'edit' => 'usuarios.edit',
                    'destroy' => 'usuarios.destroy',
                ],
                'parameters' => ['usuarios' => 'user'],
            ])->except(['show', 'update']);

            Route::put('/usuarios/{user}/contrasena-proveedor', [UserController::class, 'updateSupplierPassword'])
                ->name('usuarios.supplier_password.update');

            Route::post('/roles', 'UserController@storeRole')->name('roles.store');
            Route::delete('/roles/{role}', 'UserController@destroyRole')->name('roles.destroy');
        });

        // Fase 4: seguimiento del evento hasta su cierre (vista `event_followups`).
        Route::get('/compras/seguimiento-eventos', [EventFollowupController::class, 'index'])
            ->middleware('permission:event_followups.view')->name('event_followups.index');
        Route::patch('/compras/seguimiento-eventos/{milestone}', [EventFollowupController::class, 'update'])
            ->middleware('permission:event_followups.update')->name('event_followups.update');

        // Fase 4 (V-06): confirmación de entrega en obra (vista `site_delivery_confirmations`).
        Route::get('/obra/entregas', [SiteDeliveryController::class, 'index'])
            ->middleware('permission:site_delivery_confirmations.view')->name('site_deliveries.index');
        Route::post('/obra/entregas/{stockExit}/confirmar', [SiteDeliveryController::class, 'store'])
            ->middleware('permission:site_delivery_confirmations.register')->name('site_deliveries.store');

        // Fase 3: Bandeja de Dirección (vista `direction_authorizations`, A: Dirección y admin).
        Route::get('/direccion/bandeja', [DirectionInboxController::class, 'index'])
            ->middleware('permission:direction_authorizations.authorize')->name('direction.inbox');

        // Auditoría (`notifications_audit`): Dirección y admin.
        Route::get('/auditoria', [NotificationController::class, 'index'])
            ->middleware('permission:notifications_audit.view')->name('notifications.index');

        // Bandeja personal: cada usuario ve solo las notificaciones que recibió (el controlador filtra por
        // destinatario). Antes solo admin y Solmat, aunque la campana del encabezado sale a todos.
        Route::get('/notificaciones', [NotificationController::class, 'inbox'])->name('notifications.inbox');
        Route::post('/notificaciones/marcar-leidas', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');

        // ── Admin + Payments + Orders ─────────────────────────────────────────

        // Órdenes de Compra: permisos de la matriz (vista `purchase_orders`). Compras ve y opera solo sus OC
        // (alcance own_buyer); Gerente y Supervisor consultan las de sus proyectos (decisión 2026-11-10).
        Route::middleware('permission:purchase_orders.view')->group(function () {
            Route::get('/ordenes-de-compra', [PurchaseOrderController::class, 'index'])->name('purchase_orders.index');
            Route::get('/ordenes-de-compra/archivadas', [PurchaseOrderController::class, 'archived'])->name('purchase_orders.archived');
        });

        // Reportar entregas por folio (Recepción de OC, R): Almacén; Gerente y Supervisor, sus proyectos.
        Route::middleware('permission:purchase_order_receipts.register')->group(function () {
            Route::get('/ordenes-de-compra/reportar-entregas', [PurchaseOrderController::class, 'deliveryReport'])
                ->name('purchase_orders.delivery_report');
        });

        Route::middleware('permission:milestones.update')->group(function () {
            Route::post('/ordenes-de-compra/{purchaseOrder}/hitos/{milestone}/modificar-monto', [PurchaseOrderMilestoneController::class, 'adjustAmount'])
                ->name('purchase_orders.milestones.adjust_amount');
        });
        Route::get('/ordenes-de-compra/vencidas-entrega', [PurchaseOrderController::class, 'overdueDeliveries'])
            ->middleware('permission:purchase_orders.update')
            ->name('purchase_orders.overdue_deliveries');

        // Papelera de OC — solo admin
        Route::middleware('role:admin')->group(function () {
            Route::get('/ordenes-de-compra/eliminadas/papelera', [PurchaseOrderController::class, 'softDeleted'])->name('purchase_orders.soft_deleted');
            Route::post('/ordenes-de-compra/{id}/restaurar', [PurchaseOrderController::class, 'restore'])->name('purchase_orders.restore');
            Route::delete('/ordenes-de-compra/{id}/eliminar-permanente', [PurchaseOrderController::class, 'forceDestroy'])->name('purchase_orders.force_destroy');
        });

        // PDF con anexos: D de la matriz, solo Compras (sus OC) y admin (decisión 2026-11-10).
        Route::middleware('permission:purchase_orders.download')->group(function () {
            Route::post('/ordenes-de-compra/{purchase_order}/anexos', [PurchaseOrderController::class, 'saveAnnex'])->name('purchase_orders.annex.save');
            Route::post('/ordenes-de-compra/{purchase_order}/pdf-con-anexos', [PurchaseOrderController::class, 'downloadPdfWithAnnexes'])->name('purchase_orders.pdfWithAnnexes');
        });

        // Órdenes de Compra — escritura. Cancelar (borrar) usa X; lo demás, editar.
        Route::middleware('permission:purchase_orders.create')->group(function () {
            Route::get('/ordenes-de-compra/create', [PurchaseOrderController::class, 'create'])->name('purchase_orders.create');
            Route::get('/ordenes-de-compra/crear-desde/{purchaseRequest}', [PurchaseOrderController::class, 'createFromSolcom'])->name('purchase_orders.create_from_solcom');
            Route::post('/ordenes-de-compra', [PurchaseOrderController::class, 'store'])->name('purchase_orders.store');
        });
        Route::delete('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'destroy'])
            ->middleware('permission:purchase_orders.cancel')->name('purchase_orders.destroy');
        Route::middleware('permission:purchase_orders.update')->group(function () {
            Route::get('/ordenes-de-compra/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase_orders.edit');
            Route::put('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'update'])->name('purchase_orders.update');
            Route::patch('/ordenes-de-compra/{purchase_order}', [PurchaseOrderController::class, 'update']);
            Route::patch('/ordenes-de-compra/{purchase_order}/archivar', [PurchaseOrderController::class, 'archive'])->name('purchase_orders.archive');
            Route::patch('/ordenes-de-compra/{purchase_order}/desarchivar', [PurchaseOrderController::class, 'unarchive'])->name('purchase_orders.unarchive');
            Route::patch('/ordenes-de-compra/{purchase_order}/comprador', [PurchaseOrderController::class, 'assignBuyer'])->name('purchase_orders.buyer.update');
            // Ítems (conceptos)
            Route::post('/ordenes-de-compra/{purchase_order}/items', [PurchaseOrderController::class, 'storeItem'])->name('purchase_orders.items.store');
            Route::patch('/ordenes-de-compra/{purchase_order}/items/{item}', [PurchaseOrderController::class, 'updateItem'])->name('purchase_orders.items.update');
            Route::delete('/ordenes-de-compra/{purchase_order}/items/{item}', [PurchaseOrderController::class, 'destroyItem'])->name('purchase_orders.items.destroy');
            // Observaciones
            Route::post('/ordenes-de-compra/{purchase_order}/notes', [PurchaseOrderController::class, 'storeObservation'])->name('purchase_orders.notes.store');
            Route::patch('/ordenes-de-compra/{purchase_order}/notes/{noteIndex}', [PurchaseOrderController::class, 'updateObservation'])->name('purchase_orders.notes.update');
            Route::delete('/ordenes-de-compra/{purchase_order}/notes/{noteIndex}', [PurchaseOrderController::class, 'destroyObservation'])->name('purchase_orders.notes.destroy');
        });

        // ── Pagos y Recepción: permisos de la matriz ─────────────────────────
        // Hitos (`milestones`): consultan Gerente, Supervisor, Dirección, Almacén, Compras y Pagos; el listado
        // se acota a las OC que el usuario ve. Crean y editan Compras (sus OC) y admin; borrar usa editar.
        Route::middleware('permission:milestones.view')->group(function () {
            Route::get('/hitos', [PurchaseOrderMilestoneController::class, 'index'])->name('milestones.index');
            Route::get('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'show'])->name('milestones.show');
        });

        Route::middleware('permission:milestones.create')->group(function () {
            Route::get('/hitos/create', [PurchaseOrderMilestoneController::class, 'create'])->name('milestones.create');
            Route::post('/hitos', [PurchaseOrderMilestoneController::class, 'store'])->name('milestones.store');
        });
        Route::middleware('permission:milestones.update')->group(function () {
            Route::get('/hitos/{purchaseOrderMilestone}/edit', [PurchaseOrderMilestoneController::class, 'edit'])->name('milestones.edit');
            Route::put('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'update'])->name('milestones.update');
            Route::patch('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'update']);
            Route::delete('/hitos/{purchaseOrderMilestone}', [PurchaseOrderMilestoneController::class, 'destroy'])->name('milestones.destroy');
        });

        // ── Admin + Payments ──────────────────────────────────────────────────

        // Pagos: Autorizaciones (Pagos presenta, Dirección y admin autorizan), Por Pagar (Pagos edita) y
        // Pagado (Pagos registra el pago). Compras y Dirección consultan.
        Route::middleware('permission:payment_authorizations.view')->group(function () {
            Route::get('/pagos/autorizar', [PaymentController::class, 'index'])->name('payments.index');
        });
        Route::middleware('permission:payments_payable.view')->group(function () {
            Route::get('/pagos/por-pagar', [PaymentController::class, 'payable'])->name('payments.payable');
            Route::get('/pagos/por-pagar/exportar-excel', [PaymentController::class, 'exportPayable'])->name('payments.payable.export');
        });
        Route::middleware('permission:payments_paid.view')->group(function () {
            Route::get('/pagos/pagados', [PaymentController::class, 'paid'])->name('payments.paid');
            Route::get('/pagos/pagados/exportar-excel', [PaymentController::class, 'exportPaid'])->name('payments.paid.export');
        });
        Route::middleware('permission:payment_authorizations.authorize')->group(function () {
            Route::post('/pagos/autorizar/seleccion/sincronizar', [PaymentController::class, 'syncAuthorizationSelection'])
                ->name('payments.authorization.selection.sync');
            Route::post('/pagos/autorizar/seleccion/limpiar', [PaymentController::class, 'clearAuthorizationSelection'])
                ->name('payments.authorization.selection.clear');
            Route::post('/pagos/autorizar/multiples', [PaymentController::class, 'authorizeMultiple'])
                ->name('payments.authorize_multiple');
        });
        Route::middleware('permission:payments_payable.view')->group(function () {
            Route::post('/pagos/por-pagar/seleccion/sincronizar', [PaymentController::class, 'syncPayableSelection'])
                ->name('payments.payable.selection.sync');
            Route::post('/pagos/por-pagar/seleccion/limpiar', [PaymentController::class, 'clearPayableSelection'])
                ->name('payments.payable.selection.clear');
        });
        Route::post('/pagos/marcar-multiples-pagados', [PaymentController::class, 'markMultiplePaidWithSpei'])
            ->middleware('permission:payments_paid.register')
            ->name('payments.mark_multiple_paid_with_spei');
        Route::get('/pagos/create', [PaymentController::class, 'create'])
            ->middleware('permission:payment_authorizations.create')->name('payments.create');
        Route::get('/pagos/{payment}', [PaymentController::class, 'show'])
            ->middleware('permission:payments_payable.view')->name('payments.show');
        Route::middleware('permission:payments_payable.update')->group(function () {
            Route::get('/pagos/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
            Route::match(['put', 'patch'], '/pagos/{payment}', [PaymentController::class, 'update'])->name('payments.update');
            Route::delete('/pagos/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        });

        // Registrar un pago adicional sobre un hito: Pagos presenta (C de Autorizaciones).
        Route::post('/pagos', [PaymentController::class, 'store'])
            ->middleware('permission:payment_authorizations.create')
            ->name('payments.store');

        // Reactivación de pagos rechazados: la piden quienes ven Autorizaciones (Pagos, Compras).
        Route::middleware('permission:payment_authorizations.view')->group(function () {
            Route::patch('/pagos/{payment}/solicitar-reactivacion', [PaymentController::class, 'requestReactivation'])
                ->name('payments.request_reactivation');
        });

        // Compras (comprador de la OC) pide cambio o cancelación de una OC autorizada.
        Route::post('/ordenes-de-compra/{purchase_order}/solicitudes-cambio', [PurchaseOrderChangeRequestController::class, 'store'])
            ->middleware('permission:purchase_orders.update|purchase_orders.cancel')
            ->name('purchase_orders.change_requests.store');

        // Emisión y cierre de OC — editar OC (Compras en sus OC, admin)
        Route::middleware('permission:purchase_orders.update')->group(function () {
            Route::patch('/ordenes-de-compra/{purchase_order}/emitir', [PurchaseOrderController::class, 'emit'])->name('purchase_orders.emit');
            Route::post('/ordenes-de-compra/{purchase_order}/cerrar', [PurchaseOrderController::class, 'close'])->name('purchase_orders.close');
        });

        // Autorización de OC (Fase 4): Dirección con su usuario y admin. También reabrir una OC cerrada
        // y resolver solicitudes de cambio o cancelación de una OC autorizada.
        Route::middleware('permission:purchase_orders.authorize')->group(function () {
            Route::post('/ordenes-de-compra/solicitudes-cambio/{changeRequest}/autorizar', [PurchaseOrderChangeRequestController::class, 'approve'])
                ->name('purchase_orders.change_requests.approve');
            Route::post('/ordenes-de-compra/solicitudes-cambio/{changeRequest}/rechazar', [PurchaseOrderChangeRequestController::class, 'reject'])
                ->name('purchase_orders.change_requests.reject');
            Route::patch('/ordenes-de-compra/{purchase_order}/autorizar', [PurchaseOrderController::class, 'approve'])->name('purchase_orders.approve');
            Route::post('/ordenes-de-compra/{purchase_order}/reabrir', [PurchaseOrderController::class, 'reopen'])->name('purchase_orders.reopen');
            Route::patch('/ordenes-de-compra/{purchase_order}/autorizar-con-pagos', [PurchaseOrderController::class, 'approveWithPayments'])
                ->name('purchase_orders.approve_with_payments');
        });

        // Modo interactivo (swipable) — solo admin
        Route::middleware('role:admin')->group(function () {
            Route::get('/pagos/interactivo/autorizar', [PaymentController::class, 'interactive'])->name('payments.interactive');
            Route::post('/pagos/{payment}/swipe', [PaymentController::class, 'swipe'])->name('payments.swipe');
        });

        // Facturas: Alta de Facturas (`invoice_intake`: Compras, Recepción por decisión, admin) y Facturas
        // (`invoices`: consulta acotada por OC; Compras valida las de sus OC). Borrar una factura usa C de Alta.
        Route::get('/facturas/alta', [PaymentController::class, 'altaFacturas'])
            ->middleware('permission:invoice_intake.view')->name('payments.alta_facturas');
        Route::get('/facturas/regularizacion-documental', [PaymentController::class, 'regularization'])
            ->middleware('permission:payments_paid.view')->name('payments.regularization');
        Route::middleware('permission:invoice_intake.create')->group(function () {
            Route::post('/facturas', [PurchaseOrderInvoiceController::class, 'store'])->name('invoices.store');
        });
        Route::get('/facturas/{invoice}/download', [PurchaseOrderInvoiceController::class, 'download'])
            ->middleware('permission:invoices.view|invoice_intake.view')->name('invoices.download');
        Route::delete('/facturas/{invoice}', [PurchaseOrderInvoiceController::class, 'destroy'])
            ->middleware('permission:invoice_intake.create')->name('invoices.destroy');

        Route::middleware('permission:invoices.view')->group(function () {
            Route::get('/facturas', [PurchaseOrderInvoiceController::class, 'index'])->name('invoices.index');
            Route::get('/facturas/exportar-excel', [PurchaseOrderInvoiceController::class, 'export'])->name('invoices.export');
            Route::get('/facturas/{invoice}', [PurchaseOrderInvoiceController::class, 'show'])->name('invoices.show');
        });
        Route::get('/facturas/{invoice}/archivo/{type}', [PurchaseOrderInvoiceController::class, 'downloadFile'])
            ->whereIn('type', ['pdf', 'xml', 'evidence', 'credit_note_pdf', 'credit_note_xml'])
            ->middleware('permission:invoices.view|invoice_intake.view')
            ->name('invoices.download_file');

        Route::middleware('permission:invoices.authorize')->group(function () {
            Route::patch('/facturas/{invoice}/estatus', [PurchaseOrderInvoiceController::class, 'updateStatus'])->name('invoices.status.update');
        });

        // Evidencias de entrega en OC: suben Gerente, Supervisor y Almacén; consultan también Compras y Pagos.
        Route::post('/evidencias', [PurchaseOrderEvidenceController::class, 'store'])
            ->middleware('permission:purchase_order_evidences.create')->name('evidences.store');
        Route::get('/evidencias/{evidence}/download', [PurchaseOrderEvidenceController::class, 'download'])
            ->middleware('permission:purchase_order_evidences.view')->name('evidences.download');
        Route::delete('/evidencias/{evidence}', [PurchaseOrderEvidenceController::class, 'destroy'])
            ->middleware('permission:purchase_order_evidences.create')->name('evidences.destroy');

        // Entregas por concepto (Recepción de OC, R): Almacén; Gerente y Supervisor en sus proyectos.
        Route::middleware('permission:purchase_order_receipts.register')->group(function () {
            Route::post('/ordenes-de-compra/{purchase_order}/entregas', [PurchaseOrderController::class, 'storeDelivery'])
                ->name('purchase_orders.deliveries.store');
        });
        // Validación de hitos (Fase 4, A de `milestone_validations` y de Hitos): registrar el cumplimiento o
        // confirmar la entrega valida el hito y habilita su pago. Validan Gerente y Supervisor (sus proyectos) y Almacén.
        Route::middleware('permission:milestone_validations.authorize')->group(function () {
            Route::post('/ordenes-de-compra/{purchase_order}/cumplimientos', [PurchaseOrderMilestoneFulfillmentController::class, 'store'])
                ->name('purchase_orders.fulfillments.store');
            Route::post('/ordenes-de-compra/{purchase_order}/hitos/{milestone}/confirmar-entrega', [PurchaseOrderController::class, 'confirmMilestoneDelivery'])
                ->name('purchase_orders.milestones.confirm_delivery');
        });
        Route::middleware('role:admin')->group(function () {
            Route::delete('/ordenes-de-compra/{purchase_order}/entregas/{delivery}', [PurchaseOrderController::class, 'revertDelivery'])
                ->name('purchase_orders.deliveries.revert');
        });

        // Contrarecibo y comprobante SPEI: quienes consultan Pagado (Dirección, Compras, Pagos).
        Route::middleware('permission:payments_paid.view')->group(function () {
            Route::get('/pagos/{payment}/contrarecibo', [PaymentController::class, 'contrarecibo'])->name('payments.contrarecibo');
            Route::get('/pagos/{payment}/comprobante-spei', [PaymentController::class, 'downloadSpeiReceipt'])->name('payments.spei_receipt.download');
        });

        // AJAX: hitos de una OC (para Alta de Facturas y la vista de la OC)
        Route::middleware('permission:invoice_intake.view|milestones.view')->group(function () {
            Route::get('/ordenes-de-compra/{purchaseOrder}/hitos-json', [PurchaseOrderMilestoneController::class, 'forOrder'])->name('milestones.for_order');
        });

        // ── SOLMAT (Solicitudes de Material) ──────────────────────────────────
        // Permisos de la matriz (vista `material_requests`). Gerente y Supervisor operan solo SOLMAT de sus
        // proyectos; el controlador aplica el alcance, limita su edición a Pendiente o con cambios, deja
        // archivar y borrar a Almacén y admin, y el envío a Almacén al gerente del proyecto (decisión 2026-11-10).

        // Papelera SOLMAT — solo admin
        Route::middleware('role:admin')->group(function () {
            Route::get('/solicitudes-material/eliminadas/papelera', [MaterialRequestController::class, 'softDeleted'])
                ->name('material_requests.soft_deleted');
            Route::post('/solicitudes-material/{id}/restaurar', [MaterialRequestController::class, 'restore'])
                ->name('material_requests.restore');
            Route::delete('/solicitudes-material/{id}/eliminar-permanente', [MaterialRequestController::class, 'forceDestroy'])
                ->name('material_requests.force_destroy');
        });

        Route::middleware('permission:material_requests.view')->group(function () {
            // Rutas estáticas antes de {materialRequest}
            Route::get('/solicitudes-material/archivadas', [MaterialRequestController::class, 'archived'])
                ->name('material_requests.archived');
            Route::get('/solicitudes-material/buscar', [MaterialRequestController::class, 'jsonByFolio'])
                ->name('material_requests.lookup');
            Route::get('/solicitudes-material', [MaterialRequestController::class, 'index'])
                ->name('material_requests.index');
        });

        Route::middleware('permission:material_requests.create')->group(function () {
            Route::get('/solicitudes-material/create', [MaterialRequestController::class, 'create'])
                ->name('material_requests.create');
            Route::post('/solicitudes-material', [MaterialRequestController::class, 'store'])
                ->name('material_requests.store');
        });

        Route::middleware('permission:material_requests.update')->group(function () {
            Route::delete('/solicitudes-material/{materialRequest}', [MaterialRequestController::class, 'destroy'])
                ->whereNumber('materialRequest')
                ->name('material_requests.destroy');
            Route::patch('/solicitudes-material/{materialRequest}/archivar', [MaterialRequestController::class, 'archive'])
                ->name('material_requests.archive');
            Route::patch('/solicitudes-material/{materialRequest}/desarchivar', [MaterialRequestController::class, 'unarchive'])
                ->name('material_requests.unarchive');
            Route::get('/solicitudes-material/{materialRequest}/edit', [MaterialRequestController::class, 'edit'])
                ->whereNumber('materialRequest')
                ->name('material_requests.edit');
            Route::match(['put', 'patch'], '/solicitudes-material/{materialRequest}', [MaterialRequestController::class, 'update'])
                ->whereNumber('materialRequest')
                ->name('material_requests.update');
            Route::post('/solicitudes-material/{materialRequest}/items', [MaterialRequestController::class, 'storeItem'])
                ->name('material_requests.items.store');
            Route::patch('/solicitudes-material/{materialRequest}/items/{item}', [MaterialRequestController::class, 'updateItem'])
                ->name('material_requests.items.update');
            Route::delete('/solicitudes-material/{materialRequest}/items/{item}', [MaterialRequestController::class, 'destroyItem'])
                ->name('material_requests.items.destroy');
            Route::post('/solicitudes-material/{materialRequest}/notes', [MaterialRequestController::class, 'storeObservation'])
                ->name('material_requests.notes.store');
            Route::patch('/solicitudes-material/{materialRequest}/notes/{noteIndex}', [MaterialRequestController::class, 'updateObservation'])
                ->name('material_requests.notes.update');
            Route::delete('/solicitudes-material/{materialRequest}/notes/{noteIndex}', [MaterialRequestController::class, 'destroyObservation'])
                ->name('material_requests.notes.destroy');
        });

        Route::post('/solicitudes-material/{materialRequest}/send-to-warehouse', [MaterialRequestController::class, 'sendToWarehouse'])
            ->middleware('permission:material_requests.authorize')
            ->name('material_requests.send_to_warehouse');

        // Cambios SOLMAT: hoy Almacén pide y resuelve cambios. La solicitud del Supervisor con
        // autorización del Gerente (C y A de la matriz) es flujo nuevo de la Fase 4.
        Route::get('/solicitudes-material/solicitudes-cambio', [MaterialRequestController::class, 'changeRequestsPanel'])
            ->middleware('permission:material_request_changes.view')
            ->name('material_request_changes.index');
        // Fase 4: cambio pedido desde la obra (Supervisor o Gerente); si lo pide el Supervisor, lo autoriza el Gerente.
        Route::post('/solicitudes-material/{materialRequest}/cambio-obra', [MaterialRequestController::class, 'requestSiteChange'])
            ->middleware('permission:material_request_changes.create')
            ->name('material_requests.site_changes.store');
        Route::post('/solicitudes-material/{materialRequest}/cambio-obra/{changeNote}/decidir', [MaterialRequestController::class, 'decideSiteChange'])
            ->middleware('permission:material_request_changes.authorize')
            ->name('material_requests.site_changes.decide');
        Route::middleware('permission:material_request_changes.update')->group(function () {
            Route::post('/solicitudes-material/{materialRequest}/request-changes', [MaterialRequestController::class, 'requestChanges'])
                ->name('material_requests.request_changes');
            Route::post('/solicitudes-material/{materialRequest}/change-notes/{changeNote}/resolve', [MaterialRequestController::class, 'resolveChangeNote'])
                ->name('material_requests.change_notes.resolve');
        });

        // ── Inventario ───────────────────────────────────────────────────────
        // Existencias (`stocks`), Entradas (`stock_entries`) y Salidas (`stock_exits`). Corregir o borrar
        // movimientos y ajustar existencias usa editar, que en la matriz solo tiene admin (decisión 2026-11-10).
        Route::middleware('permission:stocks.view')->group(function () {
            Route::get('/inventario', [StockController::class, 'index'])->name('stocks.index');
            Route::get('/inventario/reportes/bajo-minimo', [StockController::class, 'lowStock'])->name('stocks.low_stock');
            Route::get('/inventario/entradas/{stockEntry}/factura', [StockController::class, 'downloadInvoice'])->name('stocks.entries.invoice.download');
            Route::get('/inventario/certificados/{stockCertificate}/descargar', [StockController::class, 'downloadCertificate'])->name('stocks.certificates.download');
        });
        Route::get('/inventario/entradas', [StockEntryController::class, 'index'])
            ->middleware('permission:stock_entries.view')->name('stocks.entries.index');
        Route::middleware('permission:stock_entries.create')->group(function () {
            Route::get('/inventario/entradas/create', [StockEntryController::class, 'create'])->name('stocks.entries.create');
            Route::post('/inventario/entradas', [StockEntryController::class, 'store'])->name('stocks.entries.store');
        });
        Route::middleware('permission:stock_entries.update')->group(function () {
            Route::match(['put', 'patch'], '/inventario/entradas/{stockEntry}', [StockEntryController::class, 'update'])->name('stocks.entries.update');
            Route::delete('/inventario/entradas/{stockEntry}', [StockEntryController::class, 'destroy'])->name('stocks.entries.destroy');
        });
        Route::middleware('permission:stock_exits.view')->group(function () {
            Route::get('/inventario/salidas', [StockExitController::class, 'index'])->name('stocks.exits.index');
            Route::get('/inventario/salidas/calendario', [StockExitController::class, 'calendar'])->name('stocks.exits.calendar');
            Route::get('/inventario/salidas/calendario/eventos', [StockExitController::class, 'calendarEvents'])->name('stocks.exits.calendar.events');
        });
        Route::middleware('permission:stock_exits.create')->group(function () {
            Route::get('/inventario/salidas/create', [StockExitController::class, 'create'])->name('stocks.exits.create');
            Route::post('/inventario/salidas', [StockExitController::class, 'store'])->name('stocks.exits.store');
        });
        Route::post('/inventario/salidas/{stockExit}/retorno', [StockExitController::class, 'returnTool'])
            ->middleware('permission:stock_exits.register')->name('stocks.exits.return');
        Route::middleware('permission:stock_exits.update')->group(function () {
            Route::match(['put', 'patch'], '/inventario/salidas/{stockExit}', [StockExitController::class, 'update'])->name('stocks.exits.update');
            Route::delete('/inventario/salidas/{stockExit}', [StockExitController::class, 'destroy'])->name('stocks.exits.destroy');
        });
        Route::post('/inventario/{concept}/ajuste', [StockController::class, 'adjust'])
            ->middleware('permission:stocks.update')->name('stocks.adjust');
        Route::get('/inventario/{concept}', [StockController::class, 'show'])
            ->middleware('permission:stocks.view')->name('stocks.show');

        // ── Vales de Material ───────────────────────────────────────────────
        // Vista `material_vouchers`; autorizar el vale es `material_voucher_authorizations.authorize` (Almacén Autoriza).
        // Fase 3: bandeja de vales por autorizar (Almacén Autoriza y admin).
        Route::get('/vales-material/por-autorizar', [MaterialVoucherController::class, 'pendingAuthorization'])
            ->middleware('permission:material_voucher_authorizations.authorize')->name('material_vouchers.pending_authorization');
        Route::middleware('permission:material_vouchers.view')->group(function () {
            Route::get('/vales-material', [MaterialVoucherController::class, 'index'])->name('material_vouchers.index');
            Route::get('/vales-material/{materialVoucher}', [MaterialVoucherController::class, 'show'])
                ->whereNumber('materialVoucher')->name('material_vouchers.show');
        });
        Route::middleware('permission:material_vouchers.create')->group(function () {
            Route::get('/vales-material/create', [MaterialVoucherController::class, 'create'])->name('material_vouchers.create');
            Route::post('/vales-material', [MaterialVoucherController::class, 'store'])->name('material_vouchers.store');
        });
        Route::middleware('permission:material_vouchers.update')->group(function () {
            Route::get('/vales-material/{materialVoucher}/edit', [MaterialVoucherController::class, 'edit'])->name('material_vouchers.edit');
            Route::match(['put', 'patch'], '/vales-material/{materialVoucher}', [MaterialVoucherController::class, 'update'])->name('material_vouchers.update');
            Route::delete('/vales-material/{materialVoucher}', [MaterialVoucherController::class, 'destroy'])->name('material_vouchers.destroy');
            Route::post('/vales-material/{materialVoucher}/items', [MaterialVoucherController::class, 'storeItem'])->name('material_vouchers.items.store');
            Route::delete('/vales-material/{materialVoucher}/items/{item}', [MaterialVoucherController::class, 'destroyItem'])->name('material_vouchers.items.destroy');
            Route::post('/vales-material/{materialVoucher}/notes', [MaterialVoucherController::class, 'storeObservation'])->name('material_vouchers.notes.store');
            Route::patch('/vales-material/{materialVoucher}/status', [MaterialVoucherController::class, 'updateStatus'])->name('material_vouchers.status.update');
        });
        Route::post('/vales-material/{materialVoucher}/authorize', [MaterialVoucherController::class, 'authorizeVoucher'])
            ->middleware('permission:material_voucher_authorizations.authorize')->name('material_vouchers.authorize');
        Route::get('/vales-material/{materialVoucher}/pdf', [MaterialVoucherController::class, 'downloadPdf'])
            ->middleware('permission:material_vouchers.download')->name('material_vouchers.pdf');

        // ── SOLCOM (Solicitudes de Compra) ────────────────────────────────────
        // Vista `purchase_requests`: Almacén la genera y la edita hasta enviarla a Compras; Compras la edita
        // después (decisión 2026-11-10). Enviar y reasignar comprador es la Pila SOLCOM de Almacén
        // (`purchase_request_queue.update`); pedir y resolver cambios, `purchase_request_changes.create`.
        Route::middleware('permission:purchase_requests.view')->group(function () {
            // Rutas estáticas antes de {purchaseRequest}
            Route::get('/solicitudes-compra/buscar-por-folio', [PurchaseRequestController::class, 'itemsJsonByFolio'])
                ->name('purchase_requests.items_json_by_folio');
            Route::get('/solicitudes-compra/archivadas', [PurchaseRequestController::class, 'archived'])
                ->name('purchase_requests.archived');
            Route::get('/solicitudes-compra', [PurchaseRequestController::class, 'index'])
                ->name('purchase_requests.index');
            Route::get('/solicitudes-compra/{purchaseRequest}/items-json', [PurchaseRequestController::class, 'itemsJson'])
                ->name('purchase_requests.items_json');
        });
        Route::get('/solicitudes-compra/solicitudes-cambio', [PurchaseRequestController::class, 'changeRequestsPanel'])
            ->middleware('permission:purchase_request_changes.view')
            ->name('purchase_request_changes.index');
        Route::middleware('permission:purchase_requests.create')->group(function () {
            Route::get('/solicitudes-compra/create', [PurchaseRequestController::class, 'create'])
                ->name('purchase_requests.create');
            Route::post('/solicitudes-compra', [PurchaseRequestController::class, 'store'])
                ->name('purchase_requests.store');
        });
        Route::middleware('permission:purchase_requests.update')->group(function () {
            Route::get('/solicitudes-compra/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])
                ->name('purchase_requests.edit');
            Route::match(['put', 'patch'], '/solicitudes-compra/{purchaseRequest}', [PurchaseRequestController::class, 'update'])
                ->name('purchase_requests.update');
            Route::delete('/solicitudes-compra/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])
                ->name('purchase_requests.destroy');
            Route::patch('/solicitudes-compra/{purchaseRequest}/archivar', [PurchaseRequestController::class, 'archive'])
                ->name('purchase_requests.archive');
            Route::patch('/solicitudes-compra/{purchaseRequest}/desarchivar', [PurchaseRequestController::class, 'unarchive'])
                ->name('purchase_requests.unarchive');
            Route::post('/solicitudes-compra/{purchaseRequest}/items', [PurchaseRequestController::class, 'storeItem'])
                ->name('purchase_requests.items.store');
            Route::delete('/solicitudes-compra/{purchaseRequest}/items/{item}', [PurchaseRequestController::class, 'destroyItem'])
                ->name('purchase_requests.items.destroy');
            Route::patch('/solicitudes-compra/{purchaseRequest}/items/{item}', [PurchaseRequestController::class, 'updateItem'])
                ->name('purchase_requests.items.update');
            Route::post('/solicitudes-compra/{purchaseRequest}/notes', [PurchaseRequestController::class, 'storeObservation'])
                ->name('purchase_requests.notes.store');
            Route::patch('/solicitudes-compra/{purchaseRequest}/notes/{noteIndex}', [PurchaseRequestController::class, 'updateObservation'])
                ->name('purchase_requests.notes.update');
            Route::delete('/solicitudes-compra/{purchaseRequest}/notes/{noteIndex}', [PurchaseRequestController::class, 'destroyObservation'])
                ->name('purchase_requests.notes.destroy');
        });
        Route::middleware('permission:purchase_request_queue.update')->group(function () {
            Route::post('/solicitudes-compra/{purchaseRequest}/send-to-purchasing', [PurchaseRequestController::class, 'sendToPurchasing'])
                ->name('purchase_requests.send_to_purchasing');
            Route::post('/solicitudes-compra/{purchaseRequest}/reassign-purchasing', [PurchaseRequestController::class, 'reassignPurchasing'])
                ->name('purchase_requests.reassign_purchasing');
        });
        Route::middleware('permission:purchase_request_changes.create')->group(function () {
            Route::post('/solicitudes-compra/{purchaseRequest}/request-changes', [PurchaseRequestController::class, 'requestChanges'])
                ->name('purchase_requests.request_changes');
            Route::post('/solicitudes-compra/{purchaseRequest}/change-notes/{changeNote}/resolve', [PurchaseRequestController::class, 'resolveChangeNote'])
                ->name('purchase_requests.change_notes.resolve');
        });
        // Fase 4: Dirección autoriza o rechaza un ajuste comercial de SOLCOM.
        Route::post('/solicitudes-compra/{purchaseRequest}/change-notes/{changeNote}/direccion', [PurchaseRequestController::class, 'decideCommercialChange'])
            ->middleware('permission:purchase_request_changes.authorize')
            ->name('purchase_requests.change_notes.decide');
        // Pila SOLCOM: SOLCOM enviadas a Compras y asignadas al usuario.
        Route::get('/compras/pila-solcom', [PurchaseRequestController::class, 'purchasingPile'])
            ->middleware('permission:purchase_request_queue.view')
            ->name('purchasing.solcom_pile');

        Route::middleware('role:admin')->group(function () {
            Route::get('/solicitudes-compra/eliminadas/papelera',
                [PurchaseRequestController::class, 'softDeleted'])
                ->name('purchase_requests.soft_deleted');

            Route::post('/solicitudes-compra/{id}/restaurar',
                [PurchaseRequestController::class, 'restore'])
                ->name('purchase_requests.restore');

            Route::delete('/solicitudes-compra/{id}/eliminar-permanente',
                [PurchaseRequestController::class, 'forceDestroy'])
                ->name('purchase_requests.force_destroy');
        });

        // ── Carga de Trabajo SOLCOM: Dirección y admin ven la de todos; cada comprador, la suya ──
        Route::middleware('permission:purchasing_workload.view')->group(function () {
            Route::get('/compras/carga-de-trabajo',
                [PurchaseRequestController::class, 'workload'])
                ->name('purchasing.workload');

        });

        // ── Almacén: Pila SOLMAT + crear SOLCOM desde SOLMAT ─────────────────
        // Vista `material_request_queue`; crear SOLCOM desde la pila es `purchase_requests.create` (Almacén).
        Route::middleware('permission:material_request_queue.view')->group(function () {
            Route::get('/almacen/pila-solmat', [PurchaseRequestController::class, 'solmatPile'])
                ->name('warehouse.solmat_pile');
            Route::post('/almacen/pila-solmat/seleccion/sincronizar', [PurchaseRequestController::class, 'syncSolmatPileSelection'])
                ->name('warehouse.solmat_pile.selection.sync');
            Route::post('/almacen/pila-solmat/seleccion/limpiar', [PurchaseRequestController::class, 'clearSolmatPileSelection'])
                ->name('warehouse.solmat_pile.selection.clear');
        });
        Route::post('/almacen/pila-solmat/{materialRequest}/compromisos', [MaterialRequestController::class, 'updateCommitments'])
            ->middleware('permission:material_request_queue.update')
            ->name('warehouse.solmat_pile.commitments.update');
        Route::middleware('permission:purchase_requests.create')->group(function () {
            Route::get('/almacen/pila-solmat/crear-solcom-consolidada', [PurchaseRequestController::class, 'createFromSolmatMulti'])
                ->name('purchase_requests.create_from_solmat_multi');
            Route::get('/almacen/pila-solmat/{materialRequest}/crear-solcom', [PurchaseRequestController::class, 'createFromSolmat'])
                ->name('purchase_requests.create_from_solmat');
        });
    });
});
