<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierLocation;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'street'       => 'nullable|string|max:255',
            'colony'       => 'nullable|string|max:255',
            'postal_code'  => 'nullable|string|max:20',
            'state'        => 'nullable|string|max:100',
            'city'         => 'nullable|string|max:100',
            'bank_name'    => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_clabe'   => 'nullable|string|max:18',
            'currency'     => 'nullable|string|in:MXN,USD,EUR',
        ]);

        $location = $supplier->locations()->create($validated);

        $this->notification->send([
            'type'         => 'SupplierLocation',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $location->id,
            'data'         => 'registró la sucursal ' . $location->name . ' para el proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Sucursal registrada correctamente.');
    }

    public function update(Request $request, Supplier $supplier, SupplierLocation $location): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'street'       => 'nullable|string|max:255',
            'colony'       => 'nullable|string|max:255',
            'postal_code'  => 'nullable|string|max:20',
            'state'        => 'nullable|string|max:100',
            'city'         => 'nullable|string|max:100',
            'bank_name'    => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_clabe'   => 'nullable|string|max:18',
            'currency'     => 'nullable|string|in:MXN,USD,EUR',
        ]);

        $location->update($validated);

        $this->notification->send([
            'type'         => 'SupplierLocation',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $location->id,
            'data'         => 'actualizó la sucursal ' . $location->name . ' del proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Sucursal actualizada correctamente.');
    }

    public function destroy(Supplier $supplier, SupplierLocation $location): RedirectResponse
    {
        $name = $location->name;
        $location->delete();

        $this->notification->send([
            'type'         => 'SupplierLocation',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $supplier->id,
            'data'         => 'eliminó la sucursal ' . $name . ' del proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Sucursal eliminada correctamente.');
    }
}

