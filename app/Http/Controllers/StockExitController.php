<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\Tool;
use App\Models\Worker;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockExitController extends Controller
{
    public function __construct(private NotificationService $notification)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $exits = StockExit::with(['concept', 'tool', 'recipientWorker', 'project', 'projectWork'])
            ->when($search, fn ($query) => $query->whereHas('concept', fn ($conceptQuery) => $conceptQuery
                ->where('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($dateFrom, fn ($query) => $query->whereDate('exited_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('exited_at', '<=', $dateTo))
            ->latest('exited_at')->paginate(25)->withQueryString();
        $workers = Worker::where('status', 'active')->orderBy('first_name')->orderBy('last_name')->get();
        $tools = Tool::whereIn('status', ['active', 'in_service'])
            ->orderBy('economic_number')
            ->get(['id', 'economic_number', 'name', 'description']);
        return view('stocks.exits.index', compact('exits', 'search', 'dateFrom', 'dateTo', 'workers', 'tools'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('stocks.exits.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'exit_type' => ['required', Rule::in(['definitive', 'tool_loan'])],
            'concept_code' => ['nullable', 'string', 'max:100'], 'tool_id' => ['nullable', 'exists:tools,id'],
            'voucher_number' => ['required', 'string', 'max:100'], 'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_worker_id' => ['nullable', 'exists:workers,id'], 'project_id' => ['nullable', 'exists:projects,id'],
            'project_work_id' => ['nullable', 'exists:project_works,id'], 'quantity' => ['required', 'numeric', 'gt:0'],
            'exited_at' => ['required', 'date'], 'expected_return_at' => ['nullable', 'date', 'after_or_equal:exited_at'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);
        $concept = ! empty($validated['concept_code']) ? Concept::where('code', $validated['concept_code'])->first() : null;
        if ($validated['exit_type'] === 'definitive' && ! $concept) {
            throw ValidationException::withMessages(['concept_code' => 'El código de suministro no existe.']);
        }
        if ($validated['exit_type'] === 'definitive' && empty($validated['recipient_name'])) {
            throw ValidationException::withMessages(['recipient_name' => 'Indique a quién se entrega el material.']);
        }
        if ($validated['exit_type'] === 'tool_loan' && (empty($validated['tool_id']) || empty($validated['recipient_worker_id']) || empty($validated['project_id']) || empty($validated['project_work_id']) || empty($validated['expected_return_at']))) {
            throw ValidationException::withMessages(['tool_id' => 'El préstamo requiere herramienta, trabajador, proyecto, obra y fecha de retorno.']);
        }
        if ($validated['exit_type'] === 'definitive') {
            $available = StockEntry::where('concept_id', $concept->id)->sum('quantity') - StockExit::where('concept_id', $concept->id)->where('exit_type', 'definitive')->sum('quantity');
            if ((float) $validated['quantity'] > (float) $available) {
                throw ValidationException::withMessages(['quantity' => 'La cantidad excede el stock disponible.']);
            }
        }
        $exit = StockExit::create([
            'concept_id' => $concept?->id, 'tool_id' => $validated['tool_id'] ?? null, 'exit_type' => $validated['exit_type'],
            'voucher_number' => $validated['voucher_number'], 'recipient_worker_id' => $validated['recipient_worker_id'] ?? null,
            'recipient_name' => $validated['recipient_name'] ?? null, 'project_id' => $validated['project_id'] ?? null,
            'project_work_id' => $validated['project_work_id'] ?? null, 'quantity' => $validated['exit_type'] === 'tool_loan' ? 1 : $validated['quantity'],
            'exited_at' => $validated['exited_at'], 'expected_return_at' => $validated['expected_return_at'] ?? null,
            'status' => $validated['exit_type'] === 'tool_loan' ? 'open' : 'completed', 'observations' => $validated['observations'] ?? null, 'created_by' => Auth::id(),
        ]);
        $this->notification->send(['type' => 'StockExit', 'action_by' => Auth::id(), 'model_action' => 'create', 'model_id' => $exit->id, 'data' => 'registró una salida de inventario #' . $exit->id . '.']);
        return redirect()->route('stocks.exits.index')->with('success', 'Salida registrada correctamente.');
    }

    public function returnTool(Request $request, StockExit $stockExit): RedirectResponse
    {
        if ($stockExit->exit_type !== 'tool_loan' || $stockExit->status !== 'open') {
            return redirect()->route('stocks.exits.index')->with('error', 'El préstamo ya fue cerrado.');
        }

        $validated = $request->validate([
            'received_at' => ['required', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        $entry = DB::transaction(function () use ($stockExit, $validated) {
            $entry = StockEntry::create([
                'tool_id' => $stockExit->tool_id,
                'entry_type' => 'tool_return',
                'quantity' => $stockExit->quantity,
                'received_at' => $validated['received_at'],
                'observations' => $validated['observations'] ?? null,
                'created_by' => Auth::id(),
            ]);
            $stockExit->update(['status' => 'returned', 'return_stock_entry_id' => $entry->id]);
            return $entry;
        });

        $this->notification->send(['type' => 'StockExit', 'action_by' => Auth::id(), 'model_action' => 'update', 'model_id' => $stockExit->id, 'data' => 'registró el retorno del préstamo de herramienta #' . $stockExit->id . '.']);

        return redirect()->route('stocks.exits.index')->with('success', 'Retorno de herramienta registrado correctamente.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StockExit $stockExit)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StockExit $stockExit)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StockExit $stockExit)
    {
        if ($stockExit->status === 'open') {
            return redirect()->route('stocks.exits.index')->with('error', 'No se puede eliminar un préstamo abierto.');
        }

        $exitId = $stockExit->id;
        $stockExit->delete();
        $this->notification->send(['type' => 'StockExit', 'action_by' => Auth::id(), 'model_action' => 'destroy', 'model_id' => $exitId, 'data' => 'eliminó la salida de inventario #' . $exitId . '.']);

        return redirect()->route('stocks.exits.index')->with('success', 'Salida eliminada correctamente.');
    }
}
