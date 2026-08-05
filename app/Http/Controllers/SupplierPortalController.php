<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\MaterialVoucher;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
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

        $invoiceStatusCounts = PurchaseOrderInvoice::query()
            ->whereHas('purchaseOrder', function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id);
            })
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $invoiceStatusSummary = [
            PurchaseOrderInvoice::STATUS_EN_PROCESO => (int) ($invoiceStatusCounts[PurchaseOrderInvoice::STATUS_EN_PROCESO] ?? 0),
            PurchaseOrderInvoice::STATUS_ACEPTADA => (int) ($invoiceStatusCounts[PurchaseOrderInvoice::STATUS_ACEPTADA] ?? 0),
            PurchaseOrderInvoice::STATUS_RECHAZADA => (int) ($invoiceStatusCounts[PurchaseOrderInvoice::STATUS_RECHAZADA] ?? 0),
        ];

        $recentInvoiceNotifications = PurchaseOrderInvoice::query()
            ->with('purchaseOrder:id,folio,supplier_id')
            ->whereHas('purchaseOrder', function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('supplier_portal.dashboard', compact(
            'supplier',
            'purchaseOrdersCount',
            'materialVouchersCount',
            'pendingMilestones',
            'recentOrders',
            'recentVouchers',
            'invoiceStatusSummary',
            'recentInvoiceNotifications',
        ));
    }

    public function downloadInvoiceGuide()
    {
        $pdf = Pdf::loadView('supplier_portal.invoice_guide_pdf')
            ->setPaper('letter', 'portrait');

        return $pdf->download('instructivo-carga-facturas.pdf');
    }

    public function purchaseOrders(Request $request): View
    {
        $supplier = $request->user()->supplier;
        $search = trim((string) $request->input('search', ''));

        $purchaseOrders = PurchaseOrder::query()
            ->with([
                'projectRelation',
                'workRelation',
                'invoices' => function ($q) {
                    $q->orderByDesc('attached_at')->orderByDesc('id');
                },
            ])
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

    public function accountStatement(Request $request): View
    {
        $supplier = $request->user()->supplier;

        $invoices = PurchaseOrderInvoice::query()
            ->with(['purchaseOrder:id,folio,elaborated_by,supplier_id'])
            ->whereHas('purchaseOrder', function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id);
            })
            ->orderByDesc('attached_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('supplier_portal.account_statement', compact('supplier', 'invoices'));
    }

    public function purchaseOrderPreview(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $supplier = $request->user()->supplier;

        abort_unless((int) $purchaseOrder->supplier_id === (int) $supplier->id, 403);

        $purchaseOrder->load([
            'supplier',
            'mobileAsset',
            'items.concept',
            'milestones',
            'purchaseRequest.materialRequest.requestedBy',
            'projectRelation',
            'workRelation',
        ]);

        return view('supplier_portal.purchase_order_preview', [
            'purchaseOrder' => $purchaseOrder,
        ]);
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
