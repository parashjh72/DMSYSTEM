<?php

namespace App\Support;

/**
 * Where a signed-in user should land. The RD-scoped roles (ASM, TSO, RD) have no
 * dashboard, so "/" and post-login resolve per ability.
 */
class Home
{
    public static function route(): string
    {
        $user = auth()->user();

        return match (true) {
            $user?->can('dashboard.view') => 'dashboard',
            $user?->can('attendance.check') => 'attendance',
            $user?->can('reports.view') => 'stock',
            $user?->can('imports.view') => 'imports.index',
            default => 'login',
        };
    }
}
