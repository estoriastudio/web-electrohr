<?php

namespace App\Http\Controllers;

use App\Models\PieceworkDailyAmount;
use App\Models\PieceworkWeeklyEntry;
use App\Models\ProjectWork;
use App\Models\Worker;
use App\Models\WorkerGroup;
use App\Services\NotificationService;
use App\Services\PieceworkCalculationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PieceworkWeeklyEntryController extends Controller
{
    public function __construct(
        private NotificationService $notification,
        private PieceworkCalculationService $calculation,
    ) {}

    public function index(Request $request): View
    {
        $weekNumber = $request->input('week_number', now()->isoWeek());
        $year = $request->input('year', now()->isoWeekYear());
        $entries = PieceworkWeeklyEntry::query()
            ->with(['worker', 'projectWork', 'workerGroup', 'foreman', 'positionCategory'])
            ->where('week_number', $weekNumber)
            ->where('year', $year)
            ->orderBy('worker_id')
            ->paginate(25)
            ->withQueryString();
        $workers = Worker::query()->where('payment_type', 'piecework')->where('status', 'active')->orderBy('last_name')->get();
        $foremen = Worker::query()->where('status', 'active')->orderBy('last_name')->get();
        $projectWorks = ProjectWork::orderBy('name')->get();
        $workerGroups = WorkerGroup::query()->where('status', 'active')->orderBy('name')->get();

        return view('human_resources.piecework.index', compact(
            'entries', 'workers', 'foremen', 'projectWorks', 'workerGroups', 'weekNumber', 'year',
        ));
    }

    public function show(PieceworkWeeklyEntry $pieceworkEntry): View
    {
        $pieceworkEntry->load(['worker', 'projectWork', 'workerGroup', 'foreman', 'positionCategory', 'dailyAmounts']);

        return view('human_resources.piecework.show', compact('pieceworkEntry'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $worker = Worker::findOrFail($data['worker_id']);
        $this->ensurePieceworkWorker($worker);
        $weekStart = $this->weekStart($data['week_number'], $data['year']);

        $entry = DB::transaction(function () use ($data, $worker, $weekStart): PieceworkWeeklyEntry {
            $group = $data['worker_group_id'] ? WorkerGroup::find($data['worker_group_id']) : $worker->currentGroup();
            $entry = PieceworkWeeklyEntry::create([
                'worker_id' => $worker->id,
                'project_work_id' => $data['project_work_id'] ?? $group?->project_work_id ?? $worker->currentProjectWork()?->id,
                'worker_group_id' => $group?->id,
                'foreman_worker_id' => $data['foreman_worker_id'] ?? null,
                'position_category_id' => $worker->position_category_id,
                'week_number' => $data['week_number'],
                'year' => $data['year'],
                'meals_amount' => $data['meals_amount'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->storeDailyAmounts($entry, $weekStart, $data['daily_amounts'] ?? []);

            return $entry;
        });
        $this->calculation->recalculate($entry);
        $this->notify($entry, 'create', "registró destajo de {$worker->first_name} {$worker->last_name}.");

        return redirect()->route('human_resources.piecework-entries.show', $entry)
            ->with('success', 'Registro de destajo creado correctamente.');
    }

    public function update(Request $request, PieceworkWeeklyEntry $pieceworkEntry): RedirectResponse
    {
        $data = $this->validatedData($request, false);
        $worker = Worker::findOrFail($data['worker_id']);
        $this->ensurePieceworkWorker($worker);
        $weekStart = $this->weekStart($pieceworkEntry->week_number, $pieceworkEntry->year);

        DB::transaction(function () use ($data, $worker, $pieceworkEntry, $weekStart): void {
            $group = $data['worker_group_id'] ? WorkerGroup::find($data['worker_group_id']) : $pieceworkEntry->workerGroup;
            $pieceworkEntry->update([
                'worker_id' => $worker->id,
                'project_work_id' => $data['project_work_id'] ?? $group?->project_work_id ?? $pieceworkEntry->project_work_id,
                'worker_group_id' => $group?->id,
                'foreman_worker_id' => $data['foreman_worker_id'] ?? null,
                'position_category_id' => $worker->position_category_id,
                'meals_amount' => $data['meals_amount'],
                'notes' => $data['notes'] ?? null,
            ]);
            $this->storeDailyAmounts($pieceworkEntry, $weekStart, $data['daily_amounts'] ?? []);
        });
        $this->calculation->recalculate($pieceworkEntry);
        $this->notify($pieceworkEntry, 'update', "actualizó el destajo de {$worker->first_name} {$worker->last_name}.");

        return redirect()->route('human_resources.piecework-entries.show', $pieceworkEntry)
            ->with('success', 'Registro de destajo actualizado correctamente.');
    }

    public function destroy(PieceworkWeeklyEntry $pieceworkEntry): RedirectResponse
    {
        $workerName = "{$pieceworkEntry->worker->first_name} {$pieceworkEntry->worker->last_name}";
        $pieceworkEntry->delete();
        $this->notify($pieceworkEntry, 'destroy', "eliminó el destajo de {$workerName}.");

        return redirect()->route('human_resources.piecework-entries.index')
            ->with('success', 'Registro de destajo eliminado correctamente.');
    }

    private function validatedData(Request $request, bool $creating = true): array
    {
        $rules = [
            'worker_id' => 'required|exists:workers,id',
            'project_work_id' => 'nullable|exists:project_works,id',
            'worker_group_id' => 'nullable|exists:worker_groups,id',
            'foreman_worker_id' => 'nullable|exists:workers,id',
            'meals_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'daily_amounts' => 'nullable|array',
            'daily_amounts.*' => 'nullable|numeric|min:0',
        ];

        if ($creating) {
            $rules += [
                'week_number' => 'required|integer|min:1|max:53',
                'year' => 'required|integer|min:2000|max:2100',
            ];
        }

        return $request->validate($rules);
    }

    private function ensurePieceworkWorker(Worker $worker): void
    {
        if ($worker->payment_type !== 'piecework') {
            throw ValidationException::withMessages(['worker_id' => 'El trabajador seleccionado no tiene pago por destajo.']);
        }
    }

    private function weekStart(int $weekNumber, int $year): Carbon
    {
        return now()->setISODate($year, $weekNumber, 1)->startOfDay();
    }

    private function storeDailyAmounts(PieceworkWeeklyEntry $entry, Carbon $weekStart, array $amounts): void
    {
        foreach (range(0, 6) as $offset) {
            $date = $weekStart->copy()->addDays($offset);
            PieceworkDailyAmount::updateOrCreate(
                [
                    'piecework_weekly_entry_id' => $entry->id,
                    'date' => $date->toDateString(),
                ],
                [
                    'day_of_week' => strtolower($date->englishDayOfWeek),
                    'amount' => $amounts[$date->toDateString()] ?? 0,
                ],
            );
        }
    }

    private function notify(PieceworkWeeklyEntry $entry, string $action, string $data): void
    {
        $this->notification->send([
            'type' => 'PieceworkWeeklyEntry',
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $entry->id,
            'data' => $data,
        ]);
    }
}