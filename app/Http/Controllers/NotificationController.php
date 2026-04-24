<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Notification::with('user')
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('action')) {
            $query->where('model_action', $request->action);
        }

        $notifications = $query->paginate(25)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function markAllRead(): JsonResponse
    {
        Notification::where('is_hidden', false)->update(['is_hidden' => true]);

        return response()->json(['success' => true, 'count' => 0]);
    }
}
