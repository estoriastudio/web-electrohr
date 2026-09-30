<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $resources = [
            'solmat' => [MaterialRequest::class, 'material_requests.show'],
            'solcom' => [PurchaseRequest::class, 'purchase_requests.show'],
            'purchase_order' => [PurchaseOrder::class, 'purchase_orders.show'],
        ];

        $validated = $request->validateWithBag('generalSearch', [
            'search_resource' => ['required', Rule::in(array_keys($resources))],
            'search_folio' => ['required', 'string', 'max:255'],
        ]);

        [$model, $route] = $resources[$validated['search_resource']];
        $resource = $model::query()->where('folio', trim($validated['search_folio']))->first();

        if (! $resource) {
            return back()->withErrors([
                'search_folio' => 'No se encontró un recurso con ese folio en la categoría seleccionada.',
            ], 'generalSearch')->withInput($validated);
        }

        return redirect()->route($route, $resource);
    }
}