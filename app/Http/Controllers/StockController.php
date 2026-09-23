<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\StockEntry;
use App\Models\StockEntryItem;
use App\Models\StockExit;
use App\Models\StockExitItem;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private NotificationService $notification)
    {
    }

    public function index(Request $request): View
    {
        $this->notifyOverdueLoans();
        $search = trim((string) $request->input('search', ''));
        $concepts = Concept::query()
            ->when($search, fn ($query) => $query->where(fn ($conceptQuery) => $conceptQuery
                ->where('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('warehouse_location', 'like', "%{$search}%")))
            ->orderBy('code')->paginate(25)->withQueryString();

        $concepts->getCollection()->each(function (Concept $concept) {
            $entries = (float) $concept->stockEntryItems()->sum('quantity');
            $exits = (float) $concept->stockExitItems()->sum('quantity');
            $concept->setAttribute('current_stock', $entries - $exits);

            $lastEntry = StockEntry::whereHas('items', fn ($query) => $query->where('concept_id', $concept->id))
                ->latest('received_at')->first(['id', 'received_at']);
            $lastExit = StockExit::whereHas('items', fn ($query) => $query->where('concept_id', $concept->id))
                ->latest('exited_at')->first(['id', 'exited_at']);
            $lastMovement = ! $lastExit || ($lastEntry && $lastEntry->received_at->gte($lastExit->exited_at))
                ? $lastEntry
                : $lastExit;
            $concept->setAttribute('last_movement_type', $lastMovement instanceof StockEntry ? 'entry' : ($lastMovement ? 'exit' : null));
            $concept->setAttribute('last_movement_at', $lastMovement instanceof StockEntry ? $lastMovement->received_at : $lastMovement?->exited_at);
        });

        $overdueLoans = StockExit::with(['tool', 'recipientWorker'])->where('exit_type', 'tool_loan')->where('status', 'open')->whereDate('expected_return_at', '<', today())->orderBy('expected_return_at')->get();

        return view('stocks.index', compact('concepts', 'overdueLoans', 'search'));
    }

    public function show(Concept $concept): View
    {
        $fiveYearsAgo = now()->subYears(5)->startOfDay();
        $entries = StockEntryItem::with(['stockEntry.certificates', 'stockEntry.createdBy'])
            ->where('concept_id', $concept->id)
            ->whereHas('stockEntry', fn ($query) => $query->where('received_at', '>=', $fiveYearsAgo))
            ->latest('id')
            ->get();
        $exits = StockExitItem::with(['stockExit.recipientWorker', 'stockExit.project', 'stockExit.projectWork', 'stockExit.createdBy'])
            ->where('concept_id', $concept->id)
            ->whereHas('stockExit', fn ($query) => $query->where('exited_at', '>=', $fiveYearsAgo))
            ->latest('id')
            ->get();
        $movements = $entries->map(fn (StockEntryItem $item) => (object) [
            'date' => $item->stockEntry->received_at,
            'direction' => 'entry',
            'label' => $item->stockEntry->entry_type === 'purchase' ? 'Compra' : 'Retorno de herramienta',
            'quantity' => $item->quantity,
            'reference' => $item->stockEntry->purchase_reference,
            'record' => $item->stockEntry,
        ])->merge($exits->map(fn (StockExitItem $item) => (object) [
            'date' => $item->stockExit->exited_at,
            'direction' => 'exit',
            'label' => 'Salida definitiva',
            'quantity' => $item->quantity,
            'reference' => $item->stockExit->voucher_number,
            'record' => $item->stockExit,
        ]))->sortByDesc('date')->values();
        $currentStock = (float) $concept->stockEntryItems()->sum('quantity')
            - (float) $concept->stockExitItems()->sum('quantity');

        return view('stocks.show', compact('concept', 'movements', 'currentStock'));
    }

    public function downloadInvoice(StockEntry $stockEntry): mixed
    {
        abort_unless($stockEntry->invoice_file_path && Storage::disk($stockEntry->invoice_disk ?: 's3')->exists($stockEntry->invoice_file_path), 404);

        return Storage::disk($stockEntry->invoice_disk ?: 's3')->download(
            $stockEntry->invoice_file_path,
            $stockEntry->invoice_file_name ?: 'factura.pdf'
        );
    }

    public function downloadCertificate(\App\Models\StockCertificate $stockCertificate): mixed
    {
        abort_unless(Storage::disk($stockCertificate->disk)->exists($stockCertificate->file_path), 404);

        return Storage::disk($stockCertificate->disk)->download($stockCertificate->file_path, $stockCertificate->file_name);
    }

    private function notifyOverdueLoans(): void
    {
        StockExit::where('exit_type', 'tool_loan')->where('status', 'open')
            ->whereDate('expected_return_at', '<', today())->whereNull('overdue_notified_at')
            ->get()->each(function (StockExit $exit) {
                $this->notification->send(['type' => 'StockExit', 'action_by' => Auth::id(), 'model_action' => 'update', 'model_id' => $exit->id, 'data' => 'detectó un préstamo de herramienta vencido #' . $exit->id . '.']);
                $exit->update(['overdue_notified_at' => now()]);
            });
    }
}
