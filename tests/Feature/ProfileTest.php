<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_own_name(): void
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $user = User::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Nome Anterior',
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($user)
            ->putJson('/api/app/profile', ['name' => 'Novo Nome'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo Nome')
            ->assertJsonPath('data.email', 'admin@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Novo Nome',
            'email' => 'admin@example.com',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'profile.updated',
        ]);
    }

    public function test_password_update_requires_the_current_password_and_invalidates_other_sessions(): void
    {
        config(['session.driver' => 'database']);

        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $user = User::factory()->create([
            'workspace_id' => $workspace->id,
            'password' => 'Current@Password2026',
        ]);

        $this->actingAs($user)
            ->putJson('/api/app/profile/password', [
                'current_password' => 'senha-incorreta',
                'password' => 'NewSecure@Password2026',
                'password_confirmation' => 'NewSecure@Password2026',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('Current@Password2026', $user->fresh()->password));

        DB::table('sessions')->insert([
            'id' => 'other-active-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->putJson('/api/app/profile/password', [
                'current_password' => 'Current@Password2026',
                'password' => 'NewSecure@Password2026',
                'password_confirmation' => 'NewSecure@Password2026',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Senha atualizada com sucesso.');

        $this->assertTrue(Hash::check('NewSecure@Password2026', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-active-session']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'profile.password_updated',
        ]);
    }

    public function test_guest_cannot_change_a_profile(): void
    {
        $this->putJson('/api/app/profile', ['name' => 'Intruso'])
            ->assertUnauthorized();

        $this->putJson('/api/app/profile/password', [
            'current_password' => 'Current@Password2026',
            'password' => 'NewSecure@Password2026',
            'password_confirmation' => 'NewSecure@Password2026',
        ])->assertUnauthorized();
    }
}
