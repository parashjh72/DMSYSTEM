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
    }
}
