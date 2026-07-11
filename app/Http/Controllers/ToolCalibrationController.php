<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolCalibration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ToolCalibrationController extends Controller
{
    public function store(Request $request, Tool $tool): RedirectResponse
    {
        $validated = $request->validate([
            'calibration_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:calibration_date'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $validated['folio'] = $this->generateNextFolio($tool);

        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $extension = $uploadedFile->getClientOriginalExtension();
            $s3Path = 'tools/' . $tool->id . '/calibrations/calibration_' . time() . '.' . $extension;
            Storage::disk('s3')->put($s3Path, file_get_contents($uploadedFile));
            $validated['file_path'] = $s3Path;
        }

        $tool->calibrations()->create($validated);

        return redirect()->route('tools.show', $tool)
            ->with('success', 'Entrada de calibración registrada correctamente.');
    }

    public function destroy(Tool $tool, ToolCalibration $toolCalibration): RedirectResponse
    {
        abort_if($toolCalibration->tool_id !== $tool->id, 404);

        if ($toolCalibration->file_path) {
            Storage::disk('s3')->delete($toolCalibration->file_path);
        }

        $toolCalibration->delete();

        return redirect()->route('tools.show', $tool)
            ->with('success', 'Entrada de calibración eliminada.');
    }

    private function generateNextFolio(Tool $tool): string
    {
        return DB::transaction(function () use ($tool) {
            $nextNumber = 1;

            $existingFolios = ToolCalibration::query()
                ->where('tool_id', $tool->id)
                ->lockForUpdate()
                ->pluck('folio');

            foreach ($existingFolios as $folio) {
                if (! is_string($folio)) {
                    continue;
                }

                if (preg_match('/-(\d+)$/', $folio, $matches) === 1) {
                    $currentNumber = (int) $matches[1];
                    if ($currentNumber >= $nextNumber) {
                        $nextNumber = $currentNumber + 1;
                    }
                }
            }

            while (ToolCalibration::query()
                ->where('tool_id', $tool->id)
                ->where('folio', sprintf('CAL-%s-%03d', $tool->economic_number, $nextNumber))
                ->lockForUpdate()
                ->exists()) {
                $nextNumber++;
            }

            return sprintf('CAL-%s-%03d', $tool->economic_number, $nextNumber);
        }, 3);
    }
}
