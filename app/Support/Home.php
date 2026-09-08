<?php

namespace App\Support;

/**
 * Where a signed-in user should land. Not everyone can see the dashboard
 * (TSO field users cannot), so "/" and post-login resolve per ability.
 */
class Home
{
    public static function route(): string
    {
        $user = auth()->user();

        return match (true) {
            $user?->can('dashboard.view') => 'dashboard',
            $user?->can('reports.view') => 'reports',
            $user?->can('imports.view') => 'imports.index',
            default => 'login',
        };
    }
}
