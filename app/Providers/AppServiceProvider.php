<?php

namespace App\Providers;

use App\Models\MaterialRequestChangeNote;
use App\Models\MaterialVoucher;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\Payment;
use App\Models\Project;
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
            $supplier = auth()->user()?->supplier;
            $hasMaterialVouchers = $supplier !== null
                && MaterialVoucher::where('supplier_id', $supplier->id)->exists();
            $activeProjectsCount = Project::where('status', 'active')->count();
            $paymentsToAuthorizeCount = Payment::query()
                ->where('status', 'por_autorizar')
                ->whereHas('milestone.purchaseOrder', function ($query) {
                    $query->where('status', 'autorizada');
                })
                ->count();
            $paymentsToPayCount = Payment::where('status', 'autorizado')->count();
            $purchaseRequestChangesPendingCount = PurchaseRequestChangeNote::query()
                ->whereNull('resolved_at')
                ->whereHas('purchaseRequest', function ($query) {
                    $query->whereNull('archived_at');
                })
                ->count();
            $materialRequestChangesPendingCount = MaterialRequestChangeNote::query()
                ->where('note_type', 'change_request')
                ->whereNull('resolved_at')
                ->whereHas('materialRequest', function ($query) {
                    $query->whereNull('archived_at');
                })
                ->count();

            $view->with(compact(
                'hasMaterialVouchers',
                'activeProjectsCount',
                'paymentsToAuthorizeCount',
                'paymentsToPayCount',
                'purchaseRequestChangesPendingCount',
                'materialRequestChangesPendingCount',
            ));
        });

        View::composer('layouts.partials._topbar', function ($view) {
            $currentUser = auth()->user();
            $topbarGlobalNotifications = Notification::with('user')
                ->where('is_hidden', false)
                ->doesntHave('recipients')
                ->latest()
                ->limit(5)
                ->get();
            $topbarGlobalUnreadCount = Notification::query()
                ->where('is_hidden', false)
                ->doesntHave('recipients')
                ->count();
            $topbarRecipientNotifications = Notification::with('user')
                ->whereHas('recipients', function ($query) use ($currentUser) {
                    $query->where('user_id', $currentUser?->id)
                        ->whereNull('read_at');
                })
                ->latest()
                ->limit(5)
                ->get();
            $topbarRecipientUnreadCount = NotificationRecipient::query()
                ->where('user_id', $currentUser?->id)
                ->whereNull('read_at')
                ->count();

            $view->with(compact(
                'topbarGlobalNotifications',
                'topbarGlobalUnreadCount',
                'topbarRecipientNotifications',
                'topbarRecipientUnreadCount',
            ));
        });
    }
}
