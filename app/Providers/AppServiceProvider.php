<?php

namespace App\Providers;

use App\Support\MailConfig;
use Illuminate\Support\Facades\Gate;
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
        // Apply admin-entered SMTP settings over the .env mail config.
        MailConfig::apply();

        // The Imports area is reachable by full importers and by RD-scoped users
        // who may only run the Sell-through import.
        Gate::define('imports.access', fn ($user) => $user->can('imports.view') || $user->can('imports.sell_through'));

        // Scheduled Reports is a Super Admin-only feature.
        Gate::define('scheduled-reports.manage', fn ($user) => $user->hasRole('Super Admin'));

        // The Returns area: RD logins raise requests, Admin / Super Admin review them.
        Gate::define('returns.access', fn ($user) => $user->can('returns.request') || $user->can('returns.review'));

        // RA Requests: TSO raises, ASM then NSM approve.
        Gate::define('ra-requests.access', fn ($user) => $user->canAny([
            'promoter_requests.create', 'promoter_requests.approve_asm', 'promoter_requests.approve_nsm',
        ]));

        // The Promoters (RA) page — roster/achievement for managers, RA Requests tab for the workflow.
        Gate::define('promoters.access', fn ($user) => $user->can('promoters.manage') || Gate::forUser($user)->allows('ra-requests.access'));

        // Field-force areas.
        // Check-in / check-out is for field TSOs only — not office roles, and not
        // Super Admin (who holds every permission but does not run a journey plan).
        Gate::define('attendance.self', fn ($user) => $user->hasRole('TSO'));
        Gate::define('attendance.access', fn ($user) => $user->hasRole('TSO') || $user->can('attendance.view_all'));
        Gate::define('pjp.access', fn ($user) => $user->canAny([
            'pjp.create', 'pjp.asm_review', 'pjp.nsm_final_approve', 'pjp.report',
        ]));
    }
}
