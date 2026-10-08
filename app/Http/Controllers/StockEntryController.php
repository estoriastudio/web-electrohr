<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\StockCertificate;
use App\Models\StockEntry;
use App\Models\StockEntryItem;
use App\Models\StockExitItem;
use App\Models\Tool;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $tools = Tool::notArchived()->whereIn('status', ['active', 'in_service'])
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
            'tool_id' => ['nullable', Rule::exists('tools', 'id')->whereNull('archived_at')],
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
                $concept = Concept::available()->where('code', $item['concept_code'])->first();
                if (! $concept) {
                    throw ValidationException::withMessages(["items.{$index}.concept_code" => 'El código de suministro no existe o no está activo.']);
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
                Storage::disk('s3')->delete($path);
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
        if ($stockEntry->is_adjustment) {
            return redirect()->route('stocks.entries.index')->with('error', 'Los ajustes manuales no se editan. Elimínelo y registre un nuevo ajuste.');
        }

        $isPurchase = $stockEntry->entry_type === 'purchase';
        $stockEntry->load(['items.concept', 'certificates']);

        $rules = [
            'received_at' => ['required', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
        if ($isPurchase) {
            $rules += [
                'purchase_reference' => ['nullable', 'string', 'max:255'],
                'invoice' => [Rule::requiredIf(! $stockEntry->invoice_file_path), 'nullable', 'file', 'mimes:pdf', 'max:20480'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0'],
                'items.*.origin_certificate' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
                'items.*.safety_certificate' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            ];
        }
        $validated = $request->validate($rules);

        if ($isPurchase && collect(array_keys($validated['items']))->map(fn ($id) => (int) $id)->sort()->values()->all() !== $stockEntry->items->pluck('id')->sort()->values()->all()) {
            throw ValidationException::withMessages(['items' => 'Los conceptos de la entrada no coinciden. Para cambiarlos, elimine la entrada y regístrela de nuevo.']);
        }

        $changes = [];
        $track = function (string $label, mixed $old, mixed $new) use (&$changes) {
            if ((string) $old !== (string) $new) {
                $changes[] = "{$label}: " . ($old === null || $old === '' ? '—' : $old) . ' → ' . ($new === null || $new === '' ? '—' : $new);
            }
        };
        $track('fecha', $stockEntry->received_at->format('d/m/Y'), \Carbon\Carbon::parse($validated['received_at'])->format('d/m/Y'));
        $track('observaciones', $stockEntry->observations, $validated['observations'] ?? null);

        $newPaths = [];
        $oldPaths = [];
        try {
            DB::transaction(function () use ($request, $validated, $stockEntry, $isPurchase, $track, &$changes, &$newPaths, &$oldPaths) {
                $attributes = [
                    'received_at' => $validated['received_at'],
                    'observations' => $validated['observations'] ?? null,
                ];

                if ($isPurchase) {
                    $track('referencia', $stockEntry->purchase_reference, $validated['purchase_reference'] ?? null);
                    $attributes['purchase_reference'] = $validated['purchase_reference'] ?? null;

                    foreach ($stockEntry->items as $item) {
                        $quantity = (float) $validated['items'][$item->id]['quantity'];
                        $delta = $quantity - (float) $item->quantity;
                        if ($delta < 0) {
                            $available = $this->availableStock($item->concept_id);
                            if ($available + $delta < 0) {
                                throw ValidationException::withMessages(["items.{$item->id}.quantity" => 'No se puede reducir la cantidad de ' . $item->concept->code . ': el stock disponible (' . $this->formatQuantity($available) . ') quedaría negativo.']);
                            }
                        }
                        if ($delta != 0) {
                            $changes[] = $item->concept->code . ': ' . $this->formatQuantity((float) $item->quantity) . ' → ' . $this->formatQuantity($quantity);
                            $item->update(['quantity' => $quantity]);
                        }

                        foreach (['origin' => 'origin_certificate', 'safety' => 'safety_certificate'] as $type => $input) {
                            if (! $request->hasFile("items.{$item->id}.{$input}")) {
                                continue;
                            }
                            $file = $request->file("items.{$item->id}.{$input}");
                            $path = $file->store("stocks/entries/{$stockEntry->id}/certificates", 's3');
                            if ($path === false) {
                                throw ValidationException::withMessages(["items.{$item->id}.{$input}" => 'No fue posible guardar el certificado en S3.']);
                            }
                            $newPaths[] = $path;
                            foreach ($stockEntry->certificates->where('concept_id', $item->concept_id)->where('certificate_type', $type) as $old) {
                                $oldPaths[] = [$old->disk, $old->file_path];
                                $old->delete();
                            }
                            StockCertificate::create([
                                'stock_entry_id' => $stockEntry->id, 'concept_id' => $item->concept_id,
                                'certificate_type' => $type, 'file_name' => $file->getClientOriginalName(),
                                'file_path' => $path, 'disk' => 's3', 'mime_type' => $file->getMimeType(),
                                'file_size' => $file->getSize(), 'uploaded_by' => Auth::id(),
                            ]);
                            $changes[] = $item->concept->code . ': certificado de ' . ($type === 'origin' ? 'origen' : 'seguridad') . ' reemplazado';
                        }
                    }
                    $attributes['quantity'] = collect($validated['items'])->sum('quantity');

                    if ($request->hasFile('invoice')) {
                        $file = $request->file('invoice');
                        $path = $file->store("stocks/entries/{$stockEntry->id}/invoices", 's3');
                        if ($path === false) {
                            throw ValidationException::withMessages(['invoice' => 'No fue posible guardar la factura en S3.']);
                        }
                        $newPaths[] = $path;
                        if ($stockEntry->invoice_file_path) {
                            $oldPaths[] = [$stockEntry->invoice_disk ?: 's3', $stockEntry->invoice_file_path];
                        }
                        $attributes += ['invoice_file_name' => $file->getClientOriginalName(), 'invoice_file_path' => $path, 'invoice_disk' => 's3'];
                        $changes[] = 'factura reemplazada';
                    }
                }

                $stockEntry->update($attributes);
            });
        } catch (\Throwable $exception) {
            foreach ($newPaths as $path) {
                Storage::disk('s3')->delete($path);
            }
            throw $exception;
        }

        foreach ($oldPaths as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        $this->notification->send(['type' => 'StockEntry', 'action_by' => Auth::id(), 'model_action' => 'update', 'model_id' => $stockEntry->id, 'data' => 'editó la entrada de inventario #' . $stockEntry->id . ($changes ? ' (' . implode('; ', $changes) . ')' : ' (sin cambios)') . '.']);

        return redirect()->route('stocks.entries.index')->with('success', 'Entrada actualizada correctamente.');
    }

    public function destroy(StockEntry $stockEntry): RedirectResponse
    {
        $stockEntry->load(['items.concept', 'certificates', 'returnedExit']);

        $paths = $stockEntry->certificates->map(fn ($certificate) => [$certificate->disk, $certificate->file_path])->all();
        if ($stockEntry->invoice_file_path) {
            $paths[] = [$stockEntry->invoice_disk ?: 's3', $stockEntry->invoice_file_path];
        }
        $summary = $stockEntry->items->map(fn ($item) => $item->concept->code . ' x ' . $this->formatQuantity((float) $item->quantity))->implode(', ');
        $reopenedExitId = $stockEntry->returnedExit?->id;

        DB::transaction(function () use ($stockEntry) {
            foreach ($stockEntry->items as $item) {
                $available = $this->availableStock($item->concept_id);
                if ($available - (float) $item->quantity < 0) {
                    throw ValidationException::withMessages(['stock' => 'No se puede eliminar la entrada: ' . $item->concept->code . ' ya tiene salidas que dependen de esta existencia (stock disponible ' . $this->formatQuantity($available) . ').']);
                }
            }
            if ($stockEntry->returnedExit) {
                $stockEntry->returnedExit->update(['status' => 'open', 'return_stock_entry_id' => null]);
            }
            $stockEntry->delete();
        });

        foreach ($paths as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        $this->notification->send(['type' => 'StockEntry', 'action_by' => Auth::id(), 'model_action' => 'destroy', 'model_id' => $stockEntry->id, 'data' => 'eliminó la entrada de inventario #' . $stockEntry->id . ($summary ? " ({$summary})" : '') . ($reopenedExitId ? ' y reabrió el préstamo #' . $reopenedExitId : '') . '.']);

        return redirect()->route('stocks.entries.index')->with('success', 'Entrada eliminada correctamente.' . ($reopenedExitId ? ' El préstamo asociado volvió a estar pendiente de retorno.' : ''));
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
