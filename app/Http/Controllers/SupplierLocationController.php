<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierLocation;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SupplierLocationController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function create(Supplier $supplier): RedirectResponse
    {
        return redirect()->route('suppliers.show', $supplier);
    }

    public function store(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'bank_name'    => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_clabe'   => 'nullable|string|max:18',
            'currency'     => 'nullable|string|in:MXN,USD,EUR',
            'account_statement' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        unset($validated['account_statement']);
        $location = $supplier->locations()->create($validated);

        $this->storeAccountStatement($request, $location);

        $this->notification->send([
            'type'         => 'SupplierLocation',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $location->id,
            'data'         => 'registró los datos bancarios ' . $location->name . ' para el proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Datos bancarios registrados correctamente.');
    }

    public function update(Request $request, Supplier $supplier, SupplierLocation $location): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'bank_name'    => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_clabe'   => 'nullable|string|max:18',
            'currency'     => 'nullable|string|in:MXN,USD,EUR',
            'account_statement' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        unset($validated['account_statement']);
        $location->update($validated);

        $this->storeAccountStatement($request, $location);

        $this->notification->send([
            'type'         => 'SupplierLocation',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $location->id,
            'data'         => 'actualizó los datos bancarios ' . $location->name . ' del proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Datos bancarios actualizados correctamente.');
    }

    public function destroy(Supplier $supplier, SupplierLocation $location): RedirectResponse
    {
        $name = $location->name;

        if ($location->account_statement_path) {
            Storage::disk('s3')->delete($location->account_statement_path);
        }

        $location->delete();

        $this->notification->send([
            'type'         => 'SupplierLocation',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $supplier->id,
            'data'         => 'eliminó los datos bancarios ' . $name . ' del proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Datos bancarios eliminados correctamente.');
    }

    private function storeAccountStatement(Request $request, SupplierLocation $location): void
    {
        if (!$request->hasFile('account_statement')) {
            return;
        }

        if ($location->account_statement_path) {
            Storage::disk('s3')->delete($location->account_statement_path);
        }

        $file = $request->file('account_statement');
        $path = 'supplier_locations/' . $location->supplier_id . '/account_statements/'
            . $location->id . '_' . time() . '.' . $file->getClientOriginalExtension();

        Storage::disk('s3')->put($path, file_get_contents($file));

        $location->update(['account_statement_path' => $path]);
    }
}

