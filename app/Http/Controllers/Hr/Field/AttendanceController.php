<?php

namespace App\Http\Controllers\Hr\Field;

use App\Http\Controllers\Controller;
use App\Services\Hr\HrFieldScope;
use App\Services\Hr\HrPermissions;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /** Asistencias del campo: padrón de la Obra, incidencias, letra de incentivo, apoyo y HE. */
    public function index(): View
    {
        $this->authorizeField();

        return view('hr.field.attendance');
    }

    private function authorizeField(): void
    {
        abort_unless(
            HrFieldScope::isUnrestricted() || auth()->user()?->hasAnyRole([HrPermissions::ROLE_SUPERVISOR, HrPermissions::ROLE_MANAGER]),
            403
        );
    }
}
