<?php

namespace App\Http\Controllers;

use App\Models\SiteDeliveryConfirmation;
use App\Models\StockExit;
use App\Services\AccessMatrix;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Fase 4 (V-06): confirmación de entrega en obra. Lista las salidas definitivas de Almacén hacia una obra
 * y deja que Gerente o Supervisor de esa obra registren lo recibido contra lo enviado. Una diferencia
 * queda como discrepancia y se avisa a Almacén.
 */
class SiteDeliveryController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(Request $request): View
    {
        $section = in_array($request->input('section'), ['pending', 'confirmed', 'discrepancy'], true)
            ? $request->input('section')
            : 'pending';

        $base = $this->visibleExits();
        $counts = [
            'pending' => (clone $base)->doesntHave('siteDeliveryConfirmation')->count(),
            'confirmed' => (clone $base)->whereHas('siteDeliveryConfirmation', fn ($c) => $c->where('status', 'received'))->count(),
            'discrepancy' => (clone $base)->whereHas('siteDeliveryConfirmation', fn ($c) => $c->where('status', 'discrepancy'))->count(),
        ];

        $exits = (clone $base)
            ->when($section === 'pending', fn ($q) => $q->doesntHave('siteDeliveryConfirmation'))
            ->when($section === 'confirmed', fn ($q) => $q->whereHas('siteDeliveryConfirmation', fn ($c) => $c->where('status', 'received')))
            ->when($section === 'discrepancy', fn ($q) => $q->whereHas('siteDeliveryConfirmation', fn ($c) => $c->where('status', 'discrepancy')))
            ->with(['project', 'projectWork', 'concept', 'items.concept', 'siteDeliveryConfirmation.items.concept', 'siteDeliveryConfirmation.confirmedBy'])
            ->orderByDesc('exited_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('site_deliveries.index', compact('exits', 'section', 'counts'));
    }

    public function store(Request $request, StockExit $stockExit): RedirectResponse
    {
        abort_unless($this->visibleExits()->whereKey($stockExit->id)->exists(), 403);
        abort_unless($this->canRegisterFor($stockExit), 403);

        if ($stockExit->siteDeliveryConfirmation()->exists()) {
            throw ValidationException::withMessages(['received' => 'Esta salida ya fue confirmada.']);
        }

        $stockExit->load('items.concept', 'concept');
        $lines = $stockExit->sentLines()->values();
        $data = $request->validate([
            'received' => ['required', 'array', 'size:'.$lines->count()],
            'received.*' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $hasDiscrepancy = false;
        foreach ($lines as $index => $line) {
            if (abs((float) $data['received'][$index] - $line['quantity']) > 0.0005) {
                $hasDiscrepancy = true;
            }
        }

        if ($hasDiscrepancy && blank($data['notes'] ?? null)) {
            throw ValidationException::withMessages(['notes' => 'Explica la diferencia entre lo enviado y lo recibido.']);
        }

        DB::transaction(function () use ($stockExit, $lines, $data, $hasDiscrepancy) {
            $confirmation = SiteDeliveryConfirmation::create([
                'stock_exit_id' => $stockExit->id,
                'status' => $hasDiscrepancy ? 'discrepancy' : 'received',
                'notes' => $data['notes'] ?? null,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            foreach ($lines as $index => $line) {
                $confirmation->items()->create([
                    'stock_exit_item_id' => $line['stock_exit_item_id'],
                    'concept_id' => $line['concept_id'],
                    'sent_quantity' => $line['quantity'],
                    'received_quantity' => (float) $data['received'][$index],
                ]);
            }
        });

        $this->notification->send([
            'type' => 'StockExit',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $stockExit->id,
            'data' => ($hasDiscrepancy ? 'reportó diferencias al recibir en obra' : 'confirmó la recepción en obra de')
                .' la salida de almacén #'.$stockExit->id.($stockExit->voucher_number ? ' (vale '.$stockExit->voucher_number.')' : '').'.',
        ]);

        return redirect()->route('site_deliveries.index')->with('success', $hasDiscrepancy
            ? 'Recepción registrada con diferencias; se avisó a Almacén.'
            : 'Recepción en obra confirmada.');
    }

    /** Salidas definitivas a una obra; Gerente y Supervisor solo ven las de sus obras. */
    private function visibleExits(): Builder
    {
        $user = Auth::user();
        $query = StockExit::query()
            ->where('exit_type', 'definitive')
            ->where('is_adjustment', false)
            ->whereNotNull('project_work_id');

        return app(AccessMatrix::class)->hasFullScope($user, 'site_delivery_confirmations')
            ? $query
            : $query->whereIn('project_work_id', $user->assignedProjectWorkIds());
    }

    /** Registra la recepción el personal de la obra (R de la matriz), o admin. */
    private function canRegisterFor(StockExit $stockExit): bool
    {
        $user = Auth::user();

        return $user->hasRole('admin')
            || ($user->can('site_delivery_confirmations.register') && in_array((int) $stockExit->project_work_id, $user->assignedProjectWorkIds(), true));
    }
}
