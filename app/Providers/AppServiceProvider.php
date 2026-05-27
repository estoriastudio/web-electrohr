<?php

namespace App\Providers;

use App\Models\Notification;
use App\Models\Project;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.partials._navbar', function ($view) {
            $activeProjectsCount = Project::where('status', 'active')->count();
            $view->with(compact('activeProjectsCount'));
        });

        View::composer('layouts.partials._topbar', function ($view) {
            $topbarNotifications = Notification::with('user')
                ->where('is_hidden', false)
                ->latest()
                ->limit(5)
                ->get();

            $topbarUnreadCount = Notification::where('is_hidden', false)->count();

            $view->with(compact('topbarNotifications', 'topbarUnreadCount'));
        });
    }
}
