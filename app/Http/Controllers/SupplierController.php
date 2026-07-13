<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Exports\SupplierExport;
use App\Imports\SupplierImport;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

/* Notificaciones */
use App\Services\NotificationService;

class SupplierController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));

        $suppliers = Supplier::withCount('purchaseOrders')
            ->with('portalUser')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('rfc_name', 'like', '%' . $search . '%')
                        ->orWhere('commercial_name', 'like', '%' . $search . '%');
                });
            })
            ->orderByRaw('COALESCE(NULLIF(rfc_name, \'\'), commercial_name) ASC')
            ->paginate(25)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     * Creation is handled via modal on the index view.
     */
    public function create()
    {
        return redirect()->route('suppliers.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rfc_name'     => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
        ]);

        $supplier = Supplier::create([
            'rfc_name' => $validated['rfc_name'],
        ]);

        if ($validated['contact_name'] ?? null) {
            $supplier->contacts()->create([
                'name'     => $validated['contact_name'] ?? null,
                'email'    => $validated['email'] ?? null,
                'phone'    => $validated['phone'] ?? null,
                'is_primary' => true,
            ]);
        }

        // Notificación
        $this->notification->send([
            'type'         => 'Supplier',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $supplier->id,
            'data'         => 'creó un nuevo proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Proveedor creado correctamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        $supplier = Supplier::withCount(['purchaseOrders', 'materialVouchers'])
            ->with([
                'purchaseOrders' => fn ($q) => $q->withCount('milestones')->orderByDesc('created_at'),
                'materialVouchers' => fn ($q) => $q->withCount('items')->with(['project', 'projectWork'])->orderByDesc('voucher_date')->orderByDesc('id'),
                'contacts',
                'locations',
                'portalUser',
                'portalAccessManager',
            ])
            ->findOrFail($supplier->id);

        $orderIds = $supplier->purchaseOrders->pluck('id');

        $milestonesCount = $supplier->purchaseOrders->sum('milestones_count');

        $saldoPagado = \App\Models\Payment::whereHas('milestone', fn ($q) => $q->whereIn('purchase_order_id', $orderIds))
            ->where('status', 'pagado')
            ->sum('amount');

        $totalOrdenado  = $supplier->purchaseOrders->sum('amount');
        $saldoPendiente = max(0, $totalOrdenado - $saldoPagado);

        $proximoHito = \App\Models\PurchaseOrderMilestone::whereIn('purchase_order_id', $orderIds)
            ->whereNotNull('due_date')
            ->whereColumn('covered_amount', '<', 'value')
            ->orderBy('due_date')
            ->first();

        return view('suppliers.show', compact(
            'supplier',
            'milestonesCount',
            'saldoPagado',
            'saldoPendiente',
            'proximoHito',
        ));
    }

    public function enablePortalAccess(Request $request, Supplier $supplier): \Illuminate\Http\RedirectResponse
    {
        $portalUserId = $supplier->portal_user_id;

        $validated = $request->validate([
            'portal_name' => 'nullable|string|max:255',
            'portal_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($portalUserId),
            ],
            'portal_password' => [
                $portalUserId ? 'nullable' : 'required',
                'confirmed',
                Password::min(8),
            ],
        ]);

        $role = Role::firstOrCreate([
            'name' => 'supplier_portal_access',
            'guard_name' => 'web',
        ]);

        DB::transaction(function () use ($supplier, $validated, $role): void {
            $portalUser = $supplier->portalUser;

            if ($portalUser) {
                $portalUser->name = $validated['portal_name'] ?: ($supplier->commercial_name ?: $supplier->rfc_name);
                $portalUser->email = $validated['portal_email'];

                if (!empty($validated['portal_password'])) {
                    $portalUser->password = Hash::make($validated['portal_password']);
                }

                $portalUser->save();
            } else {
                $portalUser = User::create([
                    'name' => $validated['portal_name'] ?: ($supplier->commercial_name ?: $supplier->rfc_name),
                    'email' => $validated['portal_email'],
                    'password' => Hash::make($validated['portal_password']),
                ]);
            }

            if (!$portalUser->hasRole($role->name)) {
                $portalUser->assignRole($role);
            }

            $supplier->portal_user_id = $portalUser->id;
            $supplier->portal_access_enabled = true;
            $supplier->portal_access_activated_at = now();
            $supplier->portal_access_deactivated_at = null;
            $supplier->portal_access_managed_by = Auth::id();
            $supplier->save();
        });

        $this->notification->send([
            'type'         => 'Supplier',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $supplier->id,
            'data'         => 'habilitó acceso al Portal para el proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Acceso al Portal habilitado correctamente.');
    }

    public function disablePortalAccess(Supplier $supplier): \Illuminate\Http\RedirectResponse
    {
        if (!$supplier->portal_user_id) {
            return redirect()->route('suppliers.show', $supplier)
                ->with('error', 'Este proveedor no tiene un usuario de portal configurado.');
        }

        $supplier->update([
            'portal_access_enabled' => false,
            'portal_access_deactivated_at' => now(),
            'portal_access_managed_by' => Auth::id(),
        ]);

        DB::table('sessions')->where('user_id', $supplier->portal_user_id)->delete();

        $this->notification->send([
            'type'         => 'Supplier',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $supplier->id,
            'data'         => 'deshabilitó acceso al Portal para el proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Acceso al Portal deshabilitado.');
    }

    public function reactivatePortalAccess(Supplier $supplier): \Illuminate\Http\RedirectResponse
    {
        if (!$supplier->portal_user_id) {
            return redirect()->route('suppliers.show', $supplier)
                ->with('error', 'Este proveedor no tiene un usuario de portal configurado.');
        }

        $supplier->update([
            'portal_access_enabled' => true,
            'portal_access_activated_at' => now(),
            'portal_access_deactivated_at' => null,
            'portal_access_managed_by' => Auth::id(),
        ]);

        $this->notification->send([
            'type'         => 'Supplier',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $supplier->id,
            'data'         => 'reactivó acceso al Portal para el proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Acceso al Portal reactivado.');
    }

    /**
     * Update only the general info fields from the inline modal.
     */
    public function updateInfo(Request $request, Supplier $supplier): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'rfc_name'        => 'required|string|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'rfc_num'         => 'nullable|string|max:20',
            'attended_by'     => 'nullable|string|max:255',
            'status'          => 'nullable|in:active,inactive,blacklisted',
        ]);

        $supplier->update($validated);

        $this->notification->send([
            'type'         => 'Supplier',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $supplier->id,
            'data'         => 'actualizó la información general del proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Información general actualizada correctamente.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'commercial_name' => 'nullable|string|max:255',
            'rfc_name'        => 'required|string|max:255',
            'rfc_num'         => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'cellphone'       => 'nullable|string|max:50',
            'address'         => 'nullable|string|max:1000',
            'attended_by'     => 'nullable|string|max:255',
            'status'          => 'nullable|in:active,inactive,blacklisted',
            'bank_name'       => 'nullable|string|max:255',
            'bank_account'    => 'nullable|string|max:50',
            'bank_clabe'      => 'nullable|string|max:18',
            'swift_code'      => 'nullable|string|max:11',
            'currency'        => 'nullable|in:MXN,USD,EUR',
        ]);

        $supplier->update($validated);

        // Notificación
        $this->notification->send([
            'type'         => 'Supplier',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $supplier->id,
            'data'         => 'actualizó la información del proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        // Notificación
        $this->notification->send([
            'type'         => 'Supplier',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $supplier->id,
            'data'         => 'eliminó al proveedor ' . ($supplier->commercial_name ?? $supplier->rfc_name),
        ]);

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor eliminado correctamente.');
    }

    /**
     * Export suppliers to Excel.
     */
    public function export()
    {
        return Excel::download(new SupplierExport, 'proveedores.xlsx');
    }

    /**
     * Import suppliers from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        Excel::import(new SupplierImport, $request->file('file'));

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedores importados correctamente.');
    }
}

