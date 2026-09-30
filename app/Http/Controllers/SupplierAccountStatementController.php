<?php

namespace App\Http\Controllers;

use App\Exports\SupplierAccountStatementExport;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierAccountStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

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
            'estado-de-cuenta-' . now()->format('Y-m-d-His') . '.xlsx',
        ));
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