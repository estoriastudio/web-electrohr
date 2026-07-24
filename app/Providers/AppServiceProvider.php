<?php

namespace App\Providers;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\Project;
use App\Models\MaterialRequestChangeNote;
use App\Models\PurchaseRequestChangeNote;
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
            $paymentsToAuthorizeCount = Payment::where('status', 'por_autorizar')->count();
            $paymentsToPayCount = Payment::where('status', 'autorizado')->count();
            $purchaseRequestChangesPendingCount = PurchaseRequestChangeNote::query()
                ->whereNull('resolved_at')
                ->whereHas('purchaseRequest', function ($query) {
                    $query->whereNull('archived_at');
                })
                ->count();
            $materialRequestChangesPendingCount = MaterialRequestChangeNote::query()
                ->whereNull('resolved_at')
                ->whereHas('materialRequest', function ($query) {
                    $query->whereNull('archived_at');
                })
                ->count();

            $view->with(compact(
                'activeProjectsCount',
                'paymentsToAuthorizeCount',
                'paymentsToPayCount',
                'purchaseRequestChangesPendingCount',
                'materialRequestChangesPendingCount',
            ));
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
