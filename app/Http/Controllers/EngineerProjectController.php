<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EngineerProjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->input('search', ''));

        $projects = Project::query()
            ->assignedTo(Auth::user())
            ->withCount('works')
            ->when($search, fn ($q) => $q->where(fn ($searchQuery) => $searchQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('my_projects.index', compact('projects', 'search'));
    }

    public function show(Request $request, Project $project): View
    {
        abort_unless(Project::query()->whereKey($project->id)->assignedTo(Auth::user())->exists(), 403);

        $project->setAttribute(
            'project_value',
            $project->current_agreement_value !== null
                ? (float) $project->current_agreement_value
                : (float) $project->works()
                    ->selectRaw('COALESCE(SUM(CAST(REPLACE(contract_value, ",", "") AS DECIMAL(15,2))), 0) as total')
                    ->value('total')
        );
        $project->load([
            'works' => fn ($q) => $q->with(['supervisorUser:id,name', 'residentUser:id,name'])->withCount('purchaseOrders')->orderBy('name'),
            'estimates' => fn ($q) => $q->with('creator')->latest('estimate_date'),
        ]);

        $materialRequests = MaterialRequest::query()
            ->where('project_id', $project->id)
            ->with('requestedBy:id,name')
            ->latest('id')
            ->paginate(15, ['*'], 'solmat_page')
            ->appends(['tab' => 'solmat']);

        $purchaseRequests = PurchaseRequest::query()
            ->where('project_id', $project->id)
            ->latest('id')
            ->paginate(15, ['*'], 'solcom_page')
            ->appends(['tab' => 'solcom']);

        $purchaseOrders = PurchaseOrder::query()
            ->where('project_id', $project->id)
            ->with('supplier:id,commercial_name,rfc_name')
            ->latest('id')
            ->paginate(15, ['*'], 'oc_page')
            ->appends(['tab' => 'oc']);

        $activeTab = in_array($request->query('tab'), ['estimates', 'solmat', 'solcom', 'oc'], true)
            ? $request->query('tab')
            : 'estimates';

        return view('my_projects.show', compact(
            'project',
            'materialRequests',
            'purchaseRequests',
            'purchaseOrders',
            'activeTab'
        ));
    }
}
