<?php

namespace App\Http\Controllers\Hr\Office;

use App\Http\Controllers\Controller;
use App\Models\HrAuditLog;
use App\Models\HrSetting;
use App\Services\Hr\HrAudit;
use App\Services\Hr\HrPermissions;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrSettingController extends Controller
{
    private const LABELS = [
        'day_off_types' => 'Libranza A/B/C',
        'sunday' => 'Regla del domingo',
        'ot_deadline_hour' => 'Hora límite de horas extra',
        'savings_payout_date' => 'Fecha de pago de la caja de ahorro',
    ];

    public function __construct(private NotificationService $notification) {}

    public function index(): View
    {
        $this->authorizeConfig();

        $settings = HrSetting::query()->pluck('value', 'key');
        $recentChanges = HrAuditLog::query()
            ->with('user')
            ->where('entity', 'Configuración')
            ->latest()
            ->limit(10)
            ->get();

        return view('hr.office.settings.index', compact('settings', 'recentChanges'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeConfig();

        $data = $request->validate([
            'day_off_types' => 'required|array',
            'day_off_types.*.pct' => 'nullable|numeric|between:0,100',
            'sunday.divisor' => 'required|integer|min:1',
            'sunday.bonus' => 'required|numeric|min:0',
            'ot_deadline_hour' => 'required|integer|between:0,23',
            'savings_payout_date' => 'required|date',
        ]);

        $dayOffTypes = [];
        foreach (['A', 'B', 'C'] as $letter) {
            $pct = $data['day_off_types'][$letter]['pct'] ?? null;
            $dayOffTypes[$letter] = ['pct' => $pct === null ? null : (float) $pct];
        }

        $newValues = [
            'day_off_types' => $dayOffTypes,
            'sunday' => [
                'divisor' => (int) $data['sunday']['divisor'],
                'bonus' => (float) $data['sunday']['bonus'],
            ],
            'ot_deadline_hour' => (int) $data['ot_deadline_hour'],
            'savings_payout_date' => $data['savings_payout_date'],
        ];

        $changed = 0;

        foreach ($newValues as $key => $value) {
            $setting = HrSetting::firstOrNew(['key' => $key]);
            $before = $setting->exists ? $setting->value : null;

            if ($setting->exists && $before == $value) {
                continue;
            }

            $setting->fill(['value' => $value, 'updated_by' => Auth::id()])->save();
            $changed++;

            HrAudit::log('Cambio de configuración', 'Configuración', $key, null, $before, $value);

            $this->notification->send([
                'type' => 'HrSetting',
                'action_by' => Auth::id(),
                'model_action' => 'update',
                'model_id' => $setting->id,
                'data' => 'actualizó la configuración de RH: '.self::LABELS[$key].'.',
            ]);
        }

        if ($changed === 0) {
            return redirect()->route('rh.office.settings.index')->with('info', 'No hubo cambios en la configuración.');
        }

        return redirect()->route('rh.office.settings.index')->with('success', 'Configuración actualizada correctamente.');
    }

    private function authorizeConfig(): void
    {
        abort_unless(HrPermissions::can('config'), 403);
    }
}
