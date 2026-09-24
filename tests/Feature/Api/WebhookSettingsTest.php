<?php

namespace Tests\Feature\Api;

use App\Contracts\BridgeMailer;
use App\Models\ApiKey;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeBridgeMailer;
use Tests\TestCase;

class WebhookSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(BridgeMailer::class, new FakeBridgeMailer);

        Setting::set('mailer_enabled', '1');
        ApiKey::create(['name' => 'default', 'key' => 'chiave-di-prova']);

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    /**
     * @return array<string, string>
     */
    protected function headers(string $key = 'chiave-di-prova'): array
    {
        return ['X-API-KEY' => $key];
    }

    public function test_the_endpoint_requires_a_valid_api_key(): void
    {
        $this->putJson('/api/webhook', ['url' => 'https://crm.example.com/hook'], $this->headers('sbagliata'))
            ->assertStatus(401);

        $this->getJson('/api/webhook')->assertStatus(401);
    }

    public function test_the_application_saves_the_default_webhook_settings(): void
    {
        $this->putJson('/api/webhook', [
            'url' => 'https://crm.example.com/hook',
            'token' => 'token-crm',
            'secret' => 'segreto-crm',
            'signature_header' => 'X-Hub-Signature-256',
        ], $this->headers())
            ->assertOk()
            ->assertExactJson([
                'message' => 'Impostazioni webhook salvate',
                'url' => 'https://crm.example.com/hook',
                'has_token' => true,
                'has_secret' => true,
                'signature_header' => 'X-Hub-Signature-256',
            ]);

        Setting::flushResolved();

        $this->assertSame('https://crm.example.com/hook', Setting::get('webhook_url'));
        $this->assertSame('token-crm', Setting::get('webhook_token'));
        $this->assertSame('segreto-crm', Setting::get('webhook_secret'));
        $this->assertSame('X-Hub-Signature-256', Setting::get('webhook_signature_header'));
    }

    public function test_the_settings_are_the_same_shown_by_the_panel(): void
    {
        $this->putJson('/api/webhook', ['url' => 'https://crm.example.com/hook', 'secret' => 'segreto-crm'], $this->headers())
            ->assertOk();

        Setting::flushResolved();

        $this->getJson('/api/webhook', $this->headers())
            ->assertOk()
            ->assertExactJson([
                'url' => 'https://crm.example.com/hook',
                'has_token' => false,
                'has_secret' => true,
                'signature_header' => 'X-Signature',
            ]);
    }

    public function test_missing_fields_clear_the_previous_values(): void
    {
        Setting::set('webhook_url', 'https://vecchio.example.com/hook');
        Setting::set('webhook_token', 'token-vecchio');
        Setting::set('webhook_secret', 'segreto-vecchio');

        $this->putJson('/api/webhook', ['url' => 'https://crm.example.com/hook'], $this->headers())->assertOk();

        Setting::flushResolved();

        $this->assertNull(Setting::get('webhook_token'));
        $this->assertNull(Setting::get('webhook_secret'));
    }

    public function test_an_invalid_request_is_rejected(): void
    {
        $this->putJson('/api/webhook', ['url' => 'ftp://crm.example.com', 'signature_header' => 'X Firma'], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url', 'signature_header']);
    }

    public function test_processed_emails_notify_the_settings_saved_through_the_api(): void
    {
        $this->putJson('/api/webhook', [
            'url' => 'https://crm.example.com/hook',
            'token' => 'token-crm',
            'secret' => 'segreto-crm',
        ], $this->headers())->assertOk();

        Setting::flushResolved();

        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://crm.example.com/hook'
            && $request->header('X-API-KEY') === ['token-crm']
            && $request->header('X-Signature') === ['sha256='.hash_hmac('sha256', $request->body(), 'segreto-crm')]);
    }
}
