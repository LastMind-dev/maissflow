<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_active_user_can_sign_in(): void
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        User::create([
            'workspace_id' => $workspace->id,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->get('/app')->assertRedirect('/login');

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('app'));

        $this->assertAuthenticated();
        $this->get('/app')->assertOk();
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        User::create([
            'workspace_id' => $workspace->id,
            'name' => 'Bloqueado',
            'email' => 'blocked@example.com',
            'password' => 'secret-password',
            'role' => 'agent',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => 'blocked@example.com',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
