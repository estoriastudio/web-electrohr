<?php

namespace App\Http\Controllers\Hr\Office;

use App\Http\Controllers\Controller;
use App\Services\Hr\HrFieldScope;
use Illuminate\View\View;

class LateOvertimeController extends Controller
{
    /** Pestaña HE fuera de plazo de la bandeja de RH. */
    public function index(): View
    {
        abort_unless(HrFieldScope::isUnrestricted(), 403);

        return view('hr.office.late-overtime.index');
    }
}
