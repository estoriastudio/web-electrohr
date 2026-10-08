<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\StockEntry;
use App\Models\StockEntryItem;
use App\Models\StockExit;
use App\Models\StockExitItem;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
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
        $exits = StockExit::with(['concept', 'tool', 'recipientWorker', 'project', 'projectWork', 'items.concept'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereHas('items.concept', fn ($conceptQuery) => $conceptQuery
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"))
                    ->orWhereHas('concept', fn ($conceptQuery) => $conceptQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%"));
            }))
            ->when($dateFrom, fn ($query) => $query->whereDate('exited_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('exited_at', '<=', $dateTo))
            ->latest('exited_at')->paginate(25)->withQueryString();
        return view('stocks.exits.index', compact('exits', 'search', 'dateFrom', 'dateTo'));
    }

    public function calendar(): View
    {
        return view('stocks.exits.calendar');
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);
        $start = \Carbon\Carbon::parse($validated['start'])->toDateString();
        $end = \Carbon\Carbon::parse($validated['end'])->toDateString();

        $events = StockExit::with(['concept', 'tool', 'recipientWorker', 'project', 'projectWork', 'returnStockEntry'])
            ->where('exit_type', 'tool_loan')
            ->where('expected_return_at', '>=', $start)
            ->where('expected_return_at', '<', $end)
            ->orderBy('expected_return_at')->orderBy('id')
            ->get()->map(function (StockExit $exit) {
                $returned = $exit->status === 'returned';
                $overdue = ! $returned && $exit->expected_return_at->lt(today());
                $worker = $exit->recipientWorker;

                return [
                    'id' => (string) $exit->id,
                    'title' => ($exit->voucher_number ? $exit->voucher_number . ' - ' : '') . ($exit->concept?->code ?: $exit->tool?->economic_number ?: 'Suministro'),
                    'start' => $exit->expected_return_at->toDateString(),
                    'allDay' => true,
                    'classNames' => [$returned ? 'bg-success' : ($overdue ? 'bg-danger' : 'bg-primary')],
                    'extendedProps' => [
                        'voucher' => $exit->voucher_number ?: '—',
                        'tool' => $exit->concept
                            ? $exit->concept->code . ' - ' . $exit->concept->description
                            : trim(($exit->tool?->economic_number ?? '') . ' - ' . ($exit->tool?->name ?: $exit->tool?->description ?: 'Sin suministro')),
                        'worker' => $exit->recipient_name ?: ($worker ? trim($worker->first_name . ' ' . $worker->last_name) : 'Sin responsable'),
                        'project' => $exit->project?->name ?: 'Sin proyecto',
                        'work' => $exit->projectWork?->name ?: 'Sin obra',
                        'quantity' => $exit->quantity,
                        'exitedAt' => $exit->exited_at->format('d/m/Y'),
                        'expectedReturnAt' => $exit->expected_return_at->format('d/m/Y'),
                        'returnedAt' => $exit->returnStockEntry?->received_at?->format('d/m/Y'),
                        'status' => $returned ? 'Devuelta' : ($overdue ? 'Retorno vencido' : 'Pendiente de retorno'),
                        'statusClass' => $returned ? 'bg-success-subtle text-success' : ($overdue ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary'),
                        'observations' => $exit->observations ?: 'Sin observaciones',
                    ],
                ];
            });

        return response()->json($events);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('stocks.exits.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'exit_type' => ['required', Rule::in(['definitive', 'tool_loan'])],
            'concept_code' => ['required_if:exit_type,tool_loan', 'nullable', 'string', 'max:100', Rule::exists('concepts', 'code')->whereNull('archived_at')->where('status', 'active')],
            'voucher_number' => ['nullable', 'string', 'max:100'], 'recipient_name' => ['nullable', 'string', 'max:150'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'project_work_id' => ['nullable', 'exists:project_works,id'], 'quantity' => ['nullable', 'numeric', 'gt:0'],
            'exited_at' => ['required', 'date'], 'expected_return_at' => ['nullable', 'date', 'after_or_equal:exited_at'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validated['exit_type'] === 'definitive' && empty($validated['recipient_name'])) {
            throw ValidationException::withMessages(['recipient_name' => 'Indique a quién se entrega el material.']);
        }
        if ($validated['exit_type'] === 'tool_loan' && (empty($validated['recipient_name']) || empty($validated['project_id']) || empty($validated['project_work_id']) || empty($validated['expected_return_at']))) {
            throw ValidationException::withMessages(['concept_code' => 'El préstamo requiere suministro, persona responsable, proyecto, obra y fecha de retorno.']);
        }
        if ($validated['exit_type'] === 'definitive') {
            $definitive = $request->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*.concept_code' => ['required', 'string', 'max:100', 'distinct'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            ]);
            $items = collect($definitive['items'])->values()->map(function (array $item, int $index) {
                $concept = Concept::available()->where('code', $item['concept_code'])->first();
                if (! $concept) {
                    throw ValidationException::withMessages(["items.{$index}.concept_code" => 'El código de suministro no existe o no está activo.']);
                }
                return ['concept' => $concept, 'quantity' => $item['quantity'], 'index' => $index];
            });
        } else {
            $items = collect();
        }

        $exit = DB::transaction(function () use ($validated, $items) {
            if ($items->isNotEmpty()) {
                foreach ($items as $item) {
                    $available = StockEntryItem::where('concept_id', $item['concept']->id)->lockForUpdate()->sum('quantity')
                        - StockExitItem::where('concept_id', $item['concept']->id)->lockForUpdate()->sum('quantity');
                    if ((float) $item['quantity'] > (float) $available) {
                        throw ValidationException::withMessages(["items.{$item['index']}.quantity" => 'La cantidad excede el stock disponible para ' . $item['concept']->code . '.']);
                    }
                }
            }

            $exit = StockExit::create([
                'concept_id' => $validated['exit_type'] === 'tool_loan' ? Concept::available()->where('code', $validated['concept_code'])->firstOrFail()->id : null,
                'tool_id' => null, 'exit_type' => $validated['exit_type'],
                'voucher_number' => $validated['voucher_number'] ?? null, 'recipient_worker_id' => null,
                'recipient_name' => $validated['recipient_name'] ?? null, 'project_id' => $validated['project_id'] ?? null,
                'project_work_id' => $validated['project_work_id'] ?? null, 'quantity' => $items->isNotEmpty() ? $items->sum('quantity') : 1,
                'exited_at' => $validated['exited_at'], 'expected_return_at' => $validated['expected_return_at'] ?? null,
                'status' => $validated['exit_type'] === 'tool_loan' ? 'open' : 'completed', 'observations' => $validated['observations'] ?? null, 'created_by' => Auth::id(),
            ]);

            $items->each(function (array $item, int $lineNumber) use ($exit) {
                $exit->items()->create([
                    'concept_id' => $item['concept']->id,
                    'line_number' => $lineNumber + 1,
                    'quantity' => $item['quantity'],
                ]);
            });

            return $exit;
        });
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
                'concept_id' => $stockExit->concept_id,
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

    public function edit(StockExit $stockExit): RedirectResponse
    {
        return redirect()->route('stocks.exits.index');
    }

    public function update(Request $request, StockExit $stockExit): RedirectResponse
    {
        if ($stockExit->is_adjustment) {
            return redirect()->route('stocks.exits.index')->with('error', 'Los ajustes manuales no se editan. Elimínelo y registre un nuevo ajuste.');
        }

        $isLoan = $stockExit->exit_type === 'tool_loan';
        $isOpenLoan = $isLoan && $stockExit->status === 'open';
        $stockExit->load('items.concept');

        $rules = [
            'voucher_number' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
        if ($isLoan) {
            $rules['recipient_name'] = ['required', 'string', 'max:150'];
            if ($isOpenLoan) {
                $rules['exited_at'] = ['required', 'date'];
                $rules['expected_return_at'] = ['required', 'date', 'after_or_equal:exited_at'];
            }
        } else {
            $rules += [
                'recipient_name' => ['required', 'string', 'max:150'],
                'exited_at' => ['required', 'date'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            ];
        }
        $validated = $request->validate($rules);

        if (! $isLoan && collect(array_keys($validated['items']))->map(fn ($id) => (int) $id)->sort()->values()->all() !== $stockExit->items->pluck('id')->sort()->values()->all()) {
            throw ValidationException::withMessages(['items' => 'Los conceptos de la salida no coinciden. Para cambiarlos, elimine la salida y regístrela de nuevo.']);
        }

        $changes = [];
        $track = function (string $label, mixed $old, mixed $new) use (&$changes) {
            if ((string) $old !== (string) $new) {
                $changes[] = "{$label}: " . ($old === null || $old === '' ? '—' : $old) . ' → ' . ($new === null || $new === '' ? '—' : $new);
            }
        };
        $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : null;

        $track('vale', $stockExit->voucher_number, $validated['voucher_number'] ?? null);
        $track('observaciones', $stockExit->observations, $validated['observations'] ?? null);

        DB::transaction(function () use ($validated, $stockExit, $isLoan, $isOpenLoan, $track, $date, &$changes) {
            $attributes = [
                'voucher_number' => $validated['voucher_number'] ?? null,
                'observations' => $validated['observations'] ?? null,
            ];

            if ($isLoan) {
                $currentRecipient = $stockExit->recipient_name ?: ($stockExit->recipientWorker
                    ? trim($stockExit->recipientWorker->first_name . ' ' . $stockExit->recipientWorker->last_name)
                    : null);
                $track('persona responsable', $currentRecipient, $validated['recipient_name']);
                $attributes['recipient_name'] = $validated['recipient_name'];
                $attributes['recipient_worker_id'] = null;
                if ($isOpenLoan) {
                    $track('fecha de entrega', $date($stockExit->exited_at), $date($validated['exited_at']));
                    $track('retorno tentativo', $date($stockExit->expected_return_at), $date($validated['expected_return_at']));
                    $attributes['exited_at'] = $validated['exited_at'];
                    $attributes['expected_return_at'] = $validated['expected_return_at'];
                    if ($stockExit->expected_return_at?->toDateString() !== \Carbon\Carbon::parse($validated['expected_return_at'])->toDateString()) {
                        $attributes['overdue_notified_at'] = null;
                    }
                }
            } else {
                $track('entregado a', $stockExit->recipient_name, $validated['recipient_name']);
                $track('fecha', $date($stockExit->exited_at), $date($validated['exited_at']));
                $attributes['recipient_name'] = $validated['recipient_name'];
                $attributes['exited_at'] = $validated['exited_at'];

                foreach ($stockExit->items as $item) {
                    $quantity = (float) $validated['items'][$item->id]['quantity'];
                    $delta = $quantity - (float) $item->quantity;
                    if ($delta > 0) {
                        $available = $this->availableStock($item->concept_id);
                        if ($delta > $available) {
                            throw ValidationException::withMessages(["items.{$item->id}.quantity" => 'La cantidad excede el stock disponible para ' . $item->concept->code . ' (' . $this->formatQuantity($available) . ').']);
                        }
                    }
                    if ($delta != 0) {
                        $changes[] = $item->concept->code . ': ' . $this->formatQuantity((float) $item->quantity) . ' → ' . $this->formatQuantity($quantity);
                        $item->update(['quantity' => $quantity]);
                    }
                }
                $attributes['quantity'] = collect($validated['items'])->sum('quantity');
            }

            $stockExit->update($attributes);
        });

        $this->notification->send(['type' => 'StockExit', 'action_by' => Auth::id(), 'model_action' => 'update', 'model_id' => $stockExit->id, 'data' => 'editó la salida de inventario #' . $stockExit->id . ($changes ? ' (' . implode('; ', $changes) . ')' : ' (sin cambios)') . '.']);

        return redirect()->route('stocks.exits.index')->with('success', 'Salida actualizada correctamente.');
    }

    public function destroy(StockExit $stockExit): RedirectResponse
    {
        $stockExit->load('items.concept');
        $summary = $stockExit->exit_type === 'tool_loan'
            ? ($stockExit->concept?->code ?: $stockExit->tool?->economic_number)
            : $stockExit->items->map(fn ($item) => $item->concept->code . ' x ' . $this->formatQuantity((float) $item->quantity))->implode(', ');

        DB::transaction(function () use ($stockExit) {
            $returnEntryId = $stockExit->return_stock_entry_id;
            $stockExit->delete();
            if ($returnEntryId) {
                StockEntry::whereKey($returnEntryId)->delete();
            }
        });

        $this->notification->send(['type' => 'StockExit', 'action_by' => Auth::id(), 'model_action' => 'destroy', 'model_id' => $stockExit->id, 'data' => 'eliminó la salida de inventario #' . $stockExit->id . ' (' . ($stockExit->voucher_number ? 'vale ' . $stockExit->voucher_number : 'sin vale') . ($summary ? ", {$summary}" : '') . ').']);

        return redirect()->route('stocks.exits.index')->with('success', 'Salida eliminada correctamente.');
    }

    private function availableStock(int $conceptId): float
    {
        return (float) StockEntryItem::where('concept_id', $conceptId)->lockForUpdate()->sum('quantity')
            - (float) StockExitItem::where('concept_id', $conceptId)->lockForUpdate()->sum('quantity');
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
