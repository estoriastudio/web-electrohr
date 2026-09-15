<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $entries = (float) $concept->stockEntries()->sum('quantity');
            $exits = (float) $concept->stockExits()->where('exit_type', 'definitive')->sum('quantity');
            $concept->setAttribute('current_stock', $entries - $exits);
        });

        $fiveYearsAgo = now()->subYears(5)->startOfDay();
        $entries = StockEntry::with(['concept', 'tool'])->where('received_at', '>=', $fiveYearsAgo)->latest('received_at')->paginate(25, ['*'], 'entries_page')->withQueryString();
        $exits = StockExit::with(['concept', 'tool', 'recipientWorker'])->where('exited_at', '>=', $fiveYearsAgo)->latest('exited_at')->paginate(25, ['*'], 'exits_page')->withQueryString();
        $overdueLoans = StockExit::with(['tool', 'recipientWorker'])->where('exit_type', 'tool_loan')->where('status', 'open')->whereDate('expected_return_at', '<', today())->orderBy('expected_return_at')->get();

        return view('stocks.index', compact('concepts', 'entries', 'exits', 'overdueLoans', 'search'));
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
