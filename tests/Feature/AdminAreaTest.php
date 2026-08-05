<?php

namespace Tests\Feature;

use App\Models\ApiKey;
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
        $this->actingAs($this->member)->get('/settings/api-keys')->assertForbidden();
        $this->actingAs($this->member)->get('/settings/webhook')->assertForbidden();
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

    public function test_admin_can_save_the_default_webhook(): void
    {
        $this->actingAs($this->admin)
            ->get('/settings/webhook')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Webhook')
                ->where('webhook.url', ''),
            );

        $this->actingAs($this->admin)
            ->put('/settings/webhook', ['url' => 'https://tuo-software.it/hooks/mail-bridge'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('https://tuo-software.it/hooks/mail-bridge', Setting::get('webhook_url'));
    }

    public function test_default_webhook_must_be_a_valid_url(): void
    {
        $this->actingAs($this->admin)
            ->put('/settings/webhook', ['url' => 'non-un-url'])
            ->assertSessionHasErrors('url');

        $this->assertNull(Setting::get('webhook_url'));
    }

    public function test_empty_url_disables_the_default_webhook(): void
    {
        Setting::set('webhook_url', 'https://tuo-software.it/hooks/mail-bridge');

        $this->actingAs($this->admin)
            ->put('/settings/webhook', ['url' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(Setting::get('webhook_url'));
    }

    public function test_admin_can_save_the_hmac_signature_settings(): void
    {
        $this->actingAs($this->admin)
            ->put('/settings/webhook', [
                'url' => 'https://tuo-software.it/hooks/mail-bridge',
                'secret' => 'chiave-hmac',
                'signature_header' => ' X-Hub-Signature-256 ',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('chiave-hmac', Setting::get('webhook_secret'));
        $this->assertSame('X-Hub-Signature-256', Setting::get('webhook_signature_header'));
    }

    public function test_the_signature_header_must_be_a_valid_header_name(): void
    {
        $this->actingAs($this->admin)
            ->put('/settings/webhook', ['url' => '', 'signature_header' => 'non valido: '])
            ->assertSessionHasErrors('signature_header');

        $this->assertNull(Setting::get('webhook_signature_header'));
    }

    public function test_empty_secret_disables_the_signature(): void
    {
        Setting::set('webhook_secret', 'chiave-hmac');

        $this->actingAs($this->admin)
            ->put('/settings/webhook', ['url' => '', 'secret' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(Setting::get('webhook_secret'));
    }

    public function test_admin_can_list_api_keys(): void
    {
        ApiKey::create(['name' => 'default', 'key' => 'chiave-default']);

        $this->actingAs($this->admin)
            ->get('/settings/api-keys')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/ApiKeys')
                ->has('apiKeys', 1)
                ->where('apiKeys.0.name', 'default')
                ->where('apiKeys.0.key', 'chiave-default'),
            );
    }

    public function test_admin_can_create_api_key(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/api-keys', ['name' => 'sito-vetrina'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $apiKey = ApiKey::where('name', 'sito-vetrina')->first();
        $this->assertNotNull($apiKey);
        $this->assertSame(ApiKey::LENGTH, strlen($apiKey->key));
    }

    public function test_api_key_names_must_be_unique(): void
    {
        ApiKey::create(['name' => 'default', 'key' => 'chiave-default']);

        $this->actingAs($this->admin)
            ->post('/settings/api-keys', ['name' => 'default'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, ApiKey::count());
    }

    public function test_admin_can_rename_api_key_without_changing_the_secret(): void
    {
        $apiKey = ApiKey::create(['name' => 'default', 'key' => 'chiave-default']);

        $this->actingAs($this->admin)
            ->put("/settings/api-keys/{$apiKey->id}", ['name' => 'produzione'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $apiKey->refresh();
        $this->assertSame('produzione', $apiKey->name);
        $this->assertSame('chiave-default', $apiKey->key);
    }

    public function test_regenerating_api_key_invalidates_previous_one(): void
    {
        $apiKey = ApiKey::create(['name' => 'default', 'key' => 'vecchia-chiave']);

        $this->actingAs($this->admin)
            ->post("/settings/api-keys/{$apiKey->id}/regenerate")
            ->assertRedirect();

        $apiKey->refresh();
        $this->assertNotSame('vecchia-chiave', $apiKey->key);
        $this->assertSame(ApiKey::LENGTH, strlen($apiKey->key));

        // La vecchia chiave non è più valida sull'API.
        Setting::set('mailer_enabled', '1');

        $this->postJson('/api/send', [], ['X-API-KEY' => 'vecchia-chiave'])
            ->assertStatus(401);
    }

    public function test_deleting_api_key_invalidates_it(): void
    {
        ApiKey::create(['name' => 'default', 'key' => 'chiave-default']);
        $revoked = ApiKey::create(['name' => 'sito-vetrina', 'key' => 'chiave-revocata']);

        $this->actingAs($this->admin)
            ->delete("/settings/api-keys/{$revoked->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($revoked->fresh());

        Setting::set('mailer_enabled', '1');

        $this->postJson('/api/send', [], ['X-API-KEY' => 'chiave-revocata'])
            ->assertStatus(401);
    }
}
