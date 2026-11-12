<?php

namespace App\Providers;

use App\Models\MaterialRequestChangeNote;
use App\Models\MaterialVoucher;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseRequestChangeNote;
use App\Models\User;
use App\Services\AccessMatrix;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccessMatrix::class, fn () => new AccessMatrix(
            database_path('data/access_matrix.php'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // admin es el superusuario: pasa cualquier permiso o Policy, incluido autorizar.
        Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);

        View::composer('layouts.partials._navbar', function ($view) {
            $supplier = auth()->user()?->supplier;
            $hasMaterialVouchers = $supplier !== null
                && MaterialVoucher::where('supplier_id', $supplier->id)->exists();
            $currentUser = auth()->user();
            // Proyectos lo ve quien consulta todos los proyectos; Mis Proyectos, quien tiene proyectos asignados
            // o solo puede ver los suyos (Gerente y Supervisor).
            $canSeeAllProjects = $currentUser !== null && app(AccessMatrix::class)->hasFullScope($currentUser, 'projects');
            $showMyProjects = $currentUser !== null && $currentUser->can('projects.view')
                && (! $canSeeAllProjects || $currentUser->hasAssignedProjects());
            $activeProjectsCount = $canSeeAllProjects ? Project::where('status', 'active')->count() : 0;
            $myActiveProjectsCount = $showMyProjects
                ? Project::where('status', 'active')->assignedTo($currentUser)->count()
                : 0;
            $paymentsToAuthorizeCount = Payment::query()
                ->whereIn('status', ['por_autorizar', 'pospuesto'])
                ->whereHas('milestone', function ($query) {
                    $query->whereDate('due_date', '<', today());
                })
                ->whereHas('milestone.purchaseOrder', function ($query) {
                    $query->where('status', 'autorizada');
                })
                ->count();
            $paymentsToPayCount = Payment::where('status', 'autorizado')->count();
            $vouchersToAuthorizeCount = $currentUser?->can('material_voucher_authorizations.authorize')
                ? MaterialVoucher::where('status', 'emitido')->count()
                : 0;
            $purchaseRequestChangesPendingCount = $currentUser?->can('purchase_request_changes.view')
                ? PurchaseRequestChangeNote::query()
                    ->whereNull('resolved_at')
                    ->whereHas('purchaseRequest', function ($query) use ($currentUser) {
                        $query->whereNull('archived_at')->visibleTo($currentUser, 'purchase_requests');
                    })
                    ->count()
                : 0;
            $materialRequestChangesPendingCount = $currentUser?->can('material_request_changes.view')
                ? MaterialRequestChangeNote::query()
                    ->whereIn('note_type', ['change_request', 'site_change'])
                    ->whereNull('resolved_at')
                    ->whereHas('materialRequest', function ($query) use ($currentUser) {
                        $query->whereNull('archived_at')->visibleTo($currentUser, 'material_requests');
                    })
                    ->count()
                : 0;

            $view->with(compact(
                'hasMaterialVouchers',
                'activeProjectsCount',
                'canSeeAllProjects',
                'showMyProjects',
                'myActiveProjectsCount',
                'paymentsToAuthorizeCount',
                'paymentsToPayCount',
                'vouchersToAuthorizeCount',
                'purchaseRequestChangesPendingCount',
                'materialRequestChangesPendingCount',
            ));
        });

        View::composer('layouts.partials._topbar', function ($view) {
            $currentUser = auth()->user();
            // La actividad global es la auditoría: solo la ve quien tiene `notifications_audit.view`.
            $canSeeAudit = (bool) $currentUser?->can('notifications_audit.view');
            $topbarGlobalNotifications = $canSeeAudit
                ? Notification::with('user')
                    ->where('is_hidden', false)
                    ->doesntHave('recipients')
                    ->latest()
                    ->limit(5)
                    ->get()
                : collect();
            $topbarGlobalUnreadCount = $canSeeAudit
                ? Notification::query()
                    ->where('is_hidden', false)
                    ->doesntHave('recipients')
                    ->count()
                : 0;
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
