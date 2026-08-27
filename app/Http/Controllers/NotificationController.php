<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationRecipient;
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

    public function inbox(Request $request): View
    {
        $notifications = Notification::with('user')
            ->whereHas('recipients', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->latest()
            ->paginate(25);

        return view('notifications.inbox', compact('notifications'));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = NotificationRecipient::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true, 'count' => $updated]);
    }
}
