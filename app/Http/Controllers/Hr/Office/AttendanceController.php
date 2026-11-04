<?php

namespace App\Http\Controllers\Hr\Office;

use App\Http\Controllers\Controller;
use App\Services\Hr\HrFieldScope;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /** Captura general de RH sobre cualquier persona. */
    public function index(): View
    {
        abort_unless(HrFieldScope::isUnrestricted(), 403);

        return view('hr.office.general-capture');
    }
}
