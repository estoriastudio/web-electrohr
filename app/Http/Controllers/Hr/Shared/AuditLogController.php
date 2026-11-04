<?php

namespace App\Http\Controllers\Hr\Shared;

use App\Http\Controllers\Controller;
use App\Services\Hr\HrPermissions;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        abort_unless(HrPermissions::can('audit'), 403);

        return view('hr.shared.audit');
    }
}
