<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TsoNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tso_layout_does_not_link_to_the_forbidden_dashboard(): void
    {
        Role::findOrCreate('TSO');
        $tso = User::factory()->create();
        $tso->assignRole('TSO');

        $this->actingAs($tso)->get(route('dashboard'))->assertForbidden();

        $this->actingAs($tso)
            ->get(route('attendance'))
            ->assertOk()
            ->assertDontSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('attendance').'"', false);
    }
}
