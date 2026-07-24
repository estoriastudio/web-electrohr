<?php

namespace App\Http\Controllers;

use App\Models\MaterialVoucher;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierPortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $supplier = $request->user()->supplier;

        $purchaseOrdersCount = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', 'autorizada')
            ->count();

        $materialVouchersCount = MaterialVoucher::query()
            ->where('supplier_id', $supplier->id)
            ->count();

        $pendingMilestones = PurchaseOrderMilestone::query()
            ->whereHas('purchaseOrder', function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id)
                    ->where('status', 'autorizada');
            })
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->whereColumn('covered_amount', '<', 'value')
            ->count();

        $recentOrders = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', 'autorizada')
            ->latest()
            ->limit(5)
            ->get();

        $recentVouchers = MaterialVoucher::query()
            ->where('supplier_id', $supplier->id)
            ->latest('voucher_date')
            ->limit(5)
            ->get();

        return view('supplier_portal.dashboard', compact(
            'supplier',
            'purchaseOrdersCount',
            'materialVouchersCount',
            'pendingMilestones',
            'recentOrders',
            'recentVouchers',
        ));
    }

    public function purchaseOrders(Request $request): View
    {
        $supplier = $request->user()->supplier;
        $search = trim((string) $request->input('search', ''));

        $purchaseOrders = PurchaseOrder::query()
            ->with(['projectRelation', 'workRelation'])
            ->withSum('invoices as invoiced_amount', 'amount')
            ->where('supplier_id', $supplier->id)
            ->where('status', 'autorizada')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('folio', 'like', '%' . $search . '%')
                        ->orWhere('project', 'like', '%' . $search . '%')
                        ->orWhere('site', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('supplier_portal.purchase_orders', compact('supplier', 'purchaseOrders', 'search'));
    }

    public function materialVouchers(Request $request): View
    {
        $supplier = $request->user()->supplier;
        $search = trim((string) $request->input('search', ''));

        $vouchers = MaterialVoucher::query()
            ->withCount('items')
            ->where('supplier_id', $supplier->id)
            ->when($search !== '', function ($q) use ($search) {
                $q->where('folio', 'like', '%' . $search . '%');
            })
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('supplier_portal.material_vouchers', compact('supplier', 'vouchers', 'search'));
    }
}
