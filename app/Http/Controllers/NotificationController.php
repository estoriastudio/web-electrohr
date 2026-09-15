<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string'],
            'action' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $activitySummary = Notification::query()
            ->selectRaw('COUNT(*) as total_actions, MIN(created_at) as first_action_at')
            ->first();
        $activeDays = $activitySummary->first_action_at
            ? Carbon::parse($activitySummary->first_action_at)->startOfDay()->diffInDays(today()) + 1
            : 1;
        $activityStats = [
            'daily_average' => $activitySummary->total_actions / $activeDays,
            'current_week' => Notification::query()
                ->where('created_at', '>=', now()->startOfWeek())
                ->count(),
            'leaders' => Notification::query()
                ->selectRaw('action_by, COUNT(*) as actions_count')
                ->whereNotNull('action_by')
                ->groupBy('action_by')
                ->orderByDesc('actions_count')
                ->limit(3)
                ->with('user:id,name')
                ->get(),
        ];

        $query = Notification::with('user')
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('action')) {
            $query->where('model_action', $request->action);
        }

        if (! empty($filters['user_id'])) {
            $query->where('action_by', $filters['user_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $users = User::query()
            ->whereIn('id', Notification::query()->whereNotNull('action_by')->select('action_by')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);
        $hasFilters = $request->hasAny(['type', 'action', 'user_id', 'start_date', 'end_date']);
        $selectedUser = ! empty($filters['user_id'])
            ? $users->firstWhere('id', (int) $filters['user_id'])
            : null;
        $userStats = $selectedUser ? [
            'total' => (clone $query)->count(),
            'created' => (clone $query)->where('model_action', 'create')->count(),
            'updated' => (clone $query)->where('model_action', 'update')->count(),
        ] : null;
        $notifications = $query->paginate(25)->withQueryString();

        return view('notifications.index', compact(
            'activityStats',
            'hasFilters',
            'notifications',
            'selectedUser',
            'userStats',
            'users',
        ));
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
