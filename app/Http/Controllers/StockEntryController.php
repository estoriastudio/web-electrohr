<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\StockCertificate;
use App\Models\StockEntry;
use App\Models\Tool;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockEntryController extends Controller
{
    public function __construct(private NotificationService $notification)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $entries = StockEntry::with(['concept', 'tool', 'certificates', 'items.concept'])
            ->when($search, fn ($query) => $query->whereHas('items.concept', fn ($conceptQuery) => $conceptQuery
                ->where('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($dateFrom, fn ($query) => $query->whereDate('received_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('received_at', '<=', $dateTo))
            ->latest('received_at')->paginate(25)->withQueryString();
        $tools = Tool::whereIn('status', ['active', 'in_service'])
            ->orderBy('economic_number')
            ->get(['id', 'economic_number', 'name', 'description']);
        return view('stocks.entries.index', compact('entries', 'search', 'dateFrom', 'dateTo', 'tools'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('stocks.entries.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_type' => ['required', Rule::in(['purchase', 'tool_return'])],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'purchase_reference' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'received_at' => ['required', 'date'],
            'invoice' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['entry_type'] === 'tool_return' && empty($validated['tool_id'])) {
            throw ValidationException::withMessages(['tool_id' => 'Seleccione la herramienta que retorna.']);
        }

        if ($validated['entry_type'] === 'purchase') {
            $purchase = $request->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*.concept_code' => ['required', 'string', 'max:100', 'distinct'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0'],
                'items.*.origin_certificate' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
                'items.*.safety_certificate' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
                'invoice' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            ]);
            $items = collect($purchase['items'])->values()->map(function (array $item, int $index) {
                $concept = Concept::where('code', $item['concept_code'])->first();
                if (! $concept) {
                    throw ValidationException::withMessages(["items.{$index}.concept_code" => 'El código de suministro no existe.']);
                }
                if ($concept->requires_origin_certificate && ! request()->hasFile("items.{$index}.origin_certificate")) {
                    throw ValidationException::withMessages(["items.{$index}.origin_certificate" => 'El certificado de origen es obligatorio para este suministro.']);
                }
                if ($concept->requires_safety_certificate && ! request()->hasFile("items.{$index}.safety_certificate")) {
                    throw ValidationException::withMessages(["items.{$index}.safety_certificate" => 'El certificado de seguridad es obligatorio para este suministro.']);
                }

                return ['concept' => $concept, 'quantity' => $item['quantity'], 'index' => $index];
            });
        } else {
            $items = collect();
        }

        $paths = [];
        try {
            $entry = DB::transaction(function () use ($request, $validated, $items, &$paths) {
                $tool = ! empty($validated['tool_id']) ? Tool::findOrFail($validated['tool_id']) : null;
                $entry = StockEntry::create([
                    'concept_id' => null,
                    'tool_id' => $tool?->id,
                    'entry_type' => $validated['entry_type'],
                    'purchase_reference' => $validated['purchase_reference'] ?? null,
                    'quantity' => $items->isNotEmpty() ? $items->sum('quantity') : $validated['quantity'],
                    'received_at' => $validated['received_at'],
                    'observations' => $validated['observations'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                if ($request->hasFile('invoice')) {
                    $file = $request->file('invoice');
                    $path = $file->store("stocks/entries/{$entry->id}/invoices", 's3');
                    if ($path === false) {
                        throw ValidationException::withMessages(['invoice' => 'No fue posible guardar la factura en S3.']);
                    }
                    $paths[] = $path;
                    $entry->update(['invoice_file_name' => $file->getClientOriginalName(), 'invoice_file_path' => $path, 'invoice_disk' => 's3']);
                }

                $items->each(function (array $item, int $lineNumber) use ($entry, $request, &$paths) {
                    $entry->items()->create([
                        'concept_id' => $item['concept']->id,
                        'line_number' => $lineNumber + 1,
                        'quantity' => $item['quantity'],
                    ]);

                    foreach (['origin' => 'origin_certificate', 'safety' => 'safety_certificate'] as $type => $input) {
                        if (! $request->hasFile("items.{$item['index']}.{$input}")) {
                            continue;
                        }
                        $file = $request->file("items.{$item['index']}.{$input}");
                        $path = $file->store("stocks/entries/{$entry->id}/certificates", 's3');
                        if ($path === false) {
                            throw ValidationException::withMessages(["items.{$item['index']}.{$input}" => 'No fue posible guardar el certificado en S3.']);
                        }
                        $paths[] = $path;
                        StockCertificate::create([
                            'stock_entry_id' => $entry->id, 'concept_id' => $item['concept']->id,
                            'certificate_type' => $type, 'file_name' => $file->getClientOriginalName(),
                            'file_path' => $path, 'disk' => 's3', 'mime_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(), 'uploaded_by' => Auth::id(),
                        ]);
                    }
                });

                return $entry;
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                \Illuminate\Support\Facades\Storage::disk('s3')->delete($path);
            }
            throw $exception;
        }

        $this->notification->send(['type' => 'StockEntry', 'action_by' => Auth::id(), 'model_action' => 'create', 'model_id' => $entry->id, 'data' => 'registró una entrada de inventario #' . $entry->id . '.']);

        return redirect()->route('stocks.entries.index')->with('success', 'Entrada registrada correctamente.');
    }

    public function show(StockEntry $stockEntry): RedirectResponse
    {
        return redirect()->route('stocks.entries.index');
    }

    public function edit(StockEntry $stockEntry): RedirectResponse
    {
        return redirect()->route('stocks.entries.index');
    }

    public function update(Request $request, StockEntry $stockEntry): RedirectResponse
    {
        return redirect()->route('stocks.entries.index')->with('error', 'Las entradas no se editan para preservar el kardex.');
    }

    public function destroy(StockEntry $stockEntry): RedirectResponse
    {
        if ($stockEntry->returnedExit()->exists()) {
            return redirect()->route('stocks.entries.index')->with('error', 'No se puede eliminar una entrada que cerró un préstamo.');
        }
        $entryId = $stockEntry->id;
        $stockEntry->delete();
        $this->notification->send(['type' => 'StockEntry', 'action_by' => Auth::id(), 'model_action' => 'destroy', 'model_id' => $entryId, 'data' => 'eliminó la entrada de inventario #' . $entryId . '.']);
        return redirect()->route('stocks.entries.index')->with('success', 'Entrada eliminada correctamente.');
    }
}
