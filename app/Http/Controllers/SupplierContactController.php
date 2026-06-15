<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierContactController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function store(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'email'      => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ]);

        if (!empty($validated['is_primary'])) {
            $supplier->contacts()->update(['is_primary' => false]);
        }

        $contact = $supplier->contacts()->create($validated);

        $this->notification->send([
            'type'         => 'SupplierContact',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $contact->id,
            'data'         => 'registró el contacto ' . $contact->name . ' para el proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Contacto registrado correctamente.');
    }

    public function update(Request $request, Supplier $supplier, SupplierContact $contact)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'email'      => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ]);

        if (!empty($validated['is_primary'])) {
            $supplier->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }

        $contact->update($validated);

        $this->notification->send([
            'type'         => 'SupplierContact',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $contact->id,
            'data'         => 'actualizó el contacto ' . $contact->name . ' del proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Contacto actualizado correctamente.');
    }

    public function destroy(Supplier $supplier, SupplierContact $contact)
    {
        $name = $contact->name;
        $contact->delete();

        $this->notification->send([
            'type'         => 'SupplierContact',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $supplier->id,
            'data'         => 'eliminó el contacto ' . $name . ' del proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Contacto eliminado correctamente.');
    }

    /**
     * Devuelve el contacto principal del proveedor en JSON (para pre-llenado en formularios de OC).
     */
    public function primaryJson(Supplier $supplier): \Illuminate\Http\JsonResponse
    {
        $contact = $supplier->contacts()->where('is_primary', true)->first()
            ?? $supplier->contacts()->first();

        if (!$contact) {
            return response()->json(['name' => null]);
        }

        return response()->json([
            'name'  => $contact->name,
            'phone' => $contact->phone,
            'email' => $contact->email,
        ]);
    }
}

