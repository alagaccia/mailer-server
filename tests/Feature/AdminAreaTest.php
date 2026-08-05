<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->member = User::factory()->create();
    }

    public function test_non_admins_cannot_access_admin_pages(): void
    {
        $this->actingAs($this->member)->get('/users')->assertForbidden();
        $this->actingAs($this->member)->get('/settings/smtp')->assertForbidden();
        $this->actingAs($this->member)->post('/settings/api-key/regenerate')->assertForbidden();
    }

    public function test_admin_can_list_users(): void
    {
        $this->actingAs($this->admin)
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/Index')
                ->has('users', 2),
            );
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->admin)
            ->post('/users', [
                'name' => 'Nuovo Utente',
                'email' => 'Nuovo@Example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'is_admin' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'nuovo@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
    }

    public function test_admin_can_update_user_without_changing_password(): void
    {
        $oldPassword = $this->member->password;

        $this->actingAs($this->admin)
            ->put("/users/{$this->member->id}", [
                'name' => 'Rinominato',
                'email' => $this->member->email,
                'password' => '',
                'is_admin' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->member->refresh();
        $this->assertSame('Rinominato', $this->member->name);
        $this->assertSame($oldPassword, $this->member->password);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $this->actingAs($this->admin)
            ->delete("/users/{$this->admin->id}")
            ->assertSessionHasErrors('user');

        $this->assertNotNull($this->admin->fresh());
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        $this->actingAs($this->admin)
            ->put("/users/{$this->admin->id}", [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'password' => '',
                'is_admin' => false,
            ])
            ->assertSessionHasErrors('is_admin');

        $this->assertTrue($this->admin->fresh()->is_admin);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $this->actingAs($this->admin)
            ->delete("/users/{$this->member->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($this->member->fresh());
    }

    public function test_admin_can_update_smtp_settings_keeping_password(): void
    {
        Setting::set('smtp_password', 'segretissima');

        $this->actingAs($this->admin)
            ->put('/settings/smtp', [
                'host' => 'smtp.example.com',
                'port' => 465,
                'username' => 'user@example.com',
                'password' => '',
                'encryption' => 'ssl',
                'from_name' => 'Mail Bridge',
                'from_address' => 'noreply@example.com',
                'reply_to' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('smtp.example.com', Setting::get('smtp_host'));
        $this->assertSame('segretissima', Setting::get('smtp_password'));
    }

    public function test_regenerating_api_key_invalidates_previous_one(): void
    {
        Setting::set('api_key', 'vecchia-chiave');

        $this->actingAs($this->admin)
            ->post('/settings/api-key/regenerate')
            ->assertRedirect();

        Setting::flushResolved();

        $newKey = Setting::get('api_key');
        $this->assertNotSame('vecchia-chiave', $newKey);
        $this->assertSame(48, strlen($newKey));

        // La vecchia chiave non è più valida sull'API.
        Setting::set('mailer_enabled', '1');

        $this->postJson('/api/send', [], ['X-API-KEY' => 'vecchia-chiave'])
            ->assertStatus(401);
    }
}
