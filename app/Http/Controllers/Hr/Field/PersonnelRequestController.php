<?php

namespace App\Http\Controllers\Hr\Field;

use App\Http\Controllers\Controller;
use App\Services\Hr\HrFieldScope;
use App\Services\Hr\HrPermissions;
use Illuminate\View\View;

class PersonnelRequestController extends Controller
{
    /** Asignación del campo: solicitud de personal por Obra y alta en obra. */
    public function index(): View
    {
        abort_unless(
            HrFieldScope::isUnrestricted() || auth()->user()?->hasAnyRole([HrPermissions::ROLE_SUPERVISOR, HrPermissions::ROLE_MANAGER]),
            403
        );

        return view('hr.field.assignments.request');
    }
}
