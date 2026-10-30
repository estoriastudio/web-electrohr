<?php

namespace App\Http\Controllers;

use App\Exports\SupplierAccountStatementExport;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierAccountStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SupplierAccountStatementController extends Controller
{
    public function __construct(private SupplierAccountStatementService $statement) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $pagination = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', Rule::in([25, 50, 100])],
        ]);
        $result = DB::transaction(fn () => $this->statement->report(
            $filters, (int) ($pagination['page'] ?? 1), (int) ($pagination['per_page'] ?? 25),
        ));

        return view('suppliers.account_statement', array_merge($result, [
            'filters' => $filters,
            'statuses' => SupplierAccountStatementService::STATUSES,
            'suppliers' => Supplier::orderBy('rfc_name')->get(['id', 'rfc_name', 'commercial_name']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'buyers' => User::whereHas('purchaseOrders')->orderBy('name')->get(['id', 'name']),
        ]));
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);

        return DB::transaction(fn () => Excel::download(
            new SupplierAccountStatementExport($this->statement, $filters),
            'estado-de-cuenta-'.now()->format('Y-m-d-His').'.xlsx',
        ));
    }

    public function exportDocuments(Request $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupplierAccountStatementService::STATUSES))],
            'export_id' => ['required', 'uuid'],
        ]);

        $invoiceIds = collect($this->statement->rows(['status' => $validated['status']]))
            ->pluck('invoice_id')
            ->filter()
            ->unique()
            ->values();
        $documents = PurchaseOrderInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->with('purchaseOrder')
            ->orderBy('purchase_order_id')
            ->orderBy('id')
            ->get();
        if ($documents->isEmpty()) {
            return response()->json(['message' => 'No se encontraron facturas para registros del estado de cuenta con ese estatus.', 'missing' => []], 422);
        }

        $disk = Storage::disk();
        $zipPath = tempnam(sys_get_temp_dir(), 'supplier-invoices-');
        if ($zipPath === false) {
            return response()->json(['message' => 'No se pudo preparar el archivo ZIP para la descarga.'], 500);
        }

        $downloadReady = false;
        $missing = [];
        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return response()->json(['message' => 'No se pudo generar el archivo ZIP.'], 500);
            }

            foreach ($documents as $invoice) {
                if (! $invoice->file_path || ! $disk->exists($invoice->file_path)) {
                    $missing[] = [
                        'order' => $invoice->purchaseOrder->folio ?: $invoice->purchase_order_id,
                        'invoice' => $invoice->folio ?: 'FACT-'.$invoice->id,
                        'url' => route('invoices.show', $invoice),
                        'reason' => $invoice->file_path ? 'El PDF no existe en el disco.' : 'La factura no tiene un PDF registrado.',
                    ];

                    continue;
                }

                $contents = $disk->get($invoice->file_path);
                $purchaseOrder = $invoice->purchaseOrder;
                $orderName = Str::slug((string) ($purchaseOrder->folio ?: 'OC')).'-'.$purchaseOrder->id;
                $fileName = basename($invoice->file_name ?: $invoice->file_path);
                $archivePath = $orderName.'/Factura-'.$invoice->id.'-'.$fileName;

                if (! is_string($contents) || ! $zip->addFromString($archivePath, $contents)) {
                    $zip->close();

                    return response()->json(['message' => 'No se pudo agregar al ZIP el PDF de la factura #'.$invoice->id.'.'], 500);
                }
            }

            $included = $zip->numFiles;
            if ($included === 0) {
                $zip->close();

                return response()->json(['message' => 'No hay PDFs disponibles para generar el ZIP. Revisa los documentos faltantes.', 'missing' => $missing], 422);
            }

            if (! $zip->close()) {
                return response()->json(['message' => 'No se pudo finalizar el archivo ZIP.'], 500);
            }

            $request->session()->put('statement_document_exports.'.$validated['export_id'], [
                'missing' => $missing,
                'included' => $included,
            ]);
            $downloadName = 'documentos-ocs-'.Str::slug($validated['status']).'-'.now()->format('Ymd_His').'.zip';
            $response = response()->download($zipPath, $downloadName, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
            $downloadReady = true;

            return $response;
        } finally {
            if (! $downloadReady && is_file($zipPath)) {
                if (! unlink($zipPath)) {
                    throw new RuntimeException('No se pudo limpiar el archivo ZIP temporal.');
                }
            }
        }
    }

    public function documentExportReport(Request $request, string $export): JsonResponse
    {
        $report = $request->session()->pull('statement_document_exports.'.$export);
        abort_if($report === null, 404, 'No se encontró el reporte de exportación.');

        return response()->json($report);
    }

    public function purchaseOrderSummary(PurchaseOrder $purchaseOrder): View
    {
        $result = DB::transaction(fn () => $this->statement->purchaseOrderSummary($purchaseOrder));

        return view('suppliers.partials._account_statement_oc_summary', array_merge($result, [
            'order' => $purchaseOrder,
            'statuses' => SupplierAccountStatementService::STATUSES,
        ]));
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'order' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'buyer_id' => ['nullable', 'integer', 'exists:users,id'],
            'currency' => ['nullable', Rule::in(['MXN', 'USD', 'EUR'])],
            'status' => ['nullable', Rule::in(array_keys(SupplierAccountStatementService::STATUSES))],
        ]);
        $validated['order'] = trim($validated['order'] ?? '');

        return $validated;
    }
}
