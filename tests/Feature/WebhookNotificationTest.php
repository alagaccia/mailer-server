<?php

namespace Tests\Feature;

use App\Contracts\BridgeMailer;
use App\Models\ApiKey;
use App\Models\Email;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeBridgeMailer;
use Tests\TestCase;

class WebhookNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected FakeBridgeMailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = new FakeBridgeMailer;
        $this->app->instance(BridgeMailer::class, $this->mailer);

        Setting::set('mailer_enabled', '1');

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    public function test_processed_email_notifies_the_default_webhook(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');

        $email = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(function (Request $request) use ($email): bool {
            return $request->url() === 'https://default.example.com/hook'
                && $request['event'] === 'email.processed'
                && ! array_key_exists('id', $request->data())
                && $request['uuid'] === $email->uuid
                && $request['recipient'] === 'a@b.it'
                && $request['status'] === Email::STATUS_SENT
                && $request['success'] === true
                && $request['error'] === null;
        });
    }

    public function test_failed_email_notifies_the_webhook_with_the_error(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');

        $this->mailer->result = 'SMTP timeout';

        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            return $request['status'] === Email::STATUS_FAILED
                && $request['success'] === false
                && $request['error'] === 'SMTP timeout';
        });
    }

    public function test_email_webhook_takes_precedence_over_the_default_one(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');

        Email::create([
            'recipient' => 'a@b.it',
            'subject' => 'S',
            'body' => 'B',
            'webhook' => 'https://specifico.example.com/hook',
        ]);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://specifico.example.com/hook');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://default.example.com/hook');
    }

    public function test_configured_token_is_sent_as_api_key_and_bearer(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');
        Setting::set('webhook_token', 'segreto-123');

        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->header('X-API-KEY') === ['segreto-123']
            && $request->header('Authorization') === ['Bearer segreto-123']);
    }

    public function test_without_a_token_no_auth_headers_are_sent(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');

        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->header('X-API-KEY') === []
            && $request->header('Authorization') === []);
    }

    public function test_configured_secret_signs_the_raw_body(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');
        Setting::set('webhook_secret', 'chiave-hmac');

        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            $atteso = 'sha256='.hash_hmac('sha256', $request->body(), 'chiave-hmac');

            return $request->header('X-Signature') === [$atteso];
        });
    }

    public function test_the_signature_header_name_can_be_customised(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');
        Setting::set('webhook_secret', 'chiave-hmac');
        Setting::set('webhook_signature_header', 'X-Hub-Signature-256');

        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->header('X-Hub-Signature-256') !== []
            && $request->header('X-Signature') === []);
    }

    public function test_without_a_secret_no_signature_is_sent(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');

        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->header('X-Signature') === []);
    }

    public function test_the_signed_body_is_still_valid_json(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');
        Setting::set('webhook_secret', 'chiave-hmac');

        $email = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(function (Request $request) use ($email): bool {
            $decoded = json_decode($request->body(), true);

            return $request->header('Content-Type') === ['application/json']
                && $decoded['uuid'] === $email->uuid
                && $decoded['event'] === 'email.processed';
        });
    }

    public function test_no_webhook_configured_sends_nothing(): void
    {
        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_queueing_an_email_does_not_notify_before_it_is_processed(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');

        ApiKey::create(['name' => 'default', 'key' => 'test-api-key']);

        $this->postJson('/api/send', [
            'to' => 'a@b.it',
            'subject' => 'S',
            'body' => 'B',
        ], ['X-API-KEY' => 'test-api-key'])->assertStatus(201);

        Http::assertNothingSent();
    }

    public function test_credentials_sent_with_the_request_are_used_for_its_webhook(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');
        Setting::set('webhook_token', 'token-di-default');
        Setting::set('webhook_secret', 'segreto-di-default');

        ApiKey::create(['name' => 'default', 'key' => 'test-api-key']);

        $this->postJson('/api/send', [
            'to' => 'a@b.it',
            'subject' => 'S',
            'body' => 'B',
            'webhook' => 'https://specifico.example.com/hook',
            'webhook_token' => 'token-specifico',
            'webhook_secret' => 'segreto-specifico',
            'webhook_signature_header' => 'X-Hub-Signature-256',
        ], ['X-API-KEY' => 'test-api-key'])->assertStatus(201);

        $this->artisan('mail:process-queue')->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            $atteso = 'sha256='.hash_hmac('sha256', $request->body(), 'segreto-specifico');

            return $request->url() === 'https://specifico.example.com/hook'
                && $request->header('X-API-KEY') === ['token-specifico']
                && $request->header('Authorization') === ['Bearer token-specifico']
                && $request->header('X-Hub-Signature-256') === [$atteso]
                && $request->header('X-Signature') === [];
        });
    }

    public function test_credentials_of_the_request_are_stored_encrypted(): void
    {
        ApiKey::create(['name' => 'default', 'key' => 'test-api-key']);

        $this->postJson('/api/send', [
            'to' => 'a@b.it',
            'subject' => 'S',
            'body' => 'B',
            'webhook' => 'https://specifico.example.com/hook',
            'webhook_token' => 'token-specifico',
            'webhook_secret' => 'segreto-specifico',
        ], ['X-API-KEY' => 'test-api-key'])->assertStatus(201);

        $raw = DB::table('emails')->first();
        $email = Email::first();

        $this->assertNotSame('token-specifico', $raw->webhook_token);
        $this->assertNotSame('segreto-specifico', $raw->webhook_secret);
        $this->assertSame('token-specifico', $email->webhook_token);
        $this->assertSame('segreto-specifico', $email->webhook_secret);
        $this->assertNull($email->webhook_signature_header);
    }

    public function test_credentials_without_a_webhook_url_are_ignored(): void
    {
        ApiKey::create(['name' => 'default', 'key' => 'test-api-key']);

        $this->postJson('/api/send', [
            'to' => 'a@b.it',
            'subject' => 'S',
            'body' => 'B',
            'webhook_secret' => 'segreto-specifico',
        ], ['X-API-KEY' => 'test-api-key'])->assertStatus(201);

        $this->assertNull(Email::first()->webhook_secret);
    }

    public function test_an_invalid_signature_header_name_is_rejected(): void
    {
        ApiKey::create(['name' => 'default', 'key' => 'test-api-key']);

        $this->postJson('/api/send', [
            'to' => 'a@b.it',
            'subject' => 'S',
            'body' => 'B',
            'webhook' => 'https://specifico.example.com/hook',
            'webhook_signature_header' => 'X Signature',
        ], ['X-API-KEY' => 'test-api-key'])
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid webhook_signature_header']);
    }

    public function test_unreachable_webhook_does_not_affect_the_email(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');

        Http::fake(['*' => Http::response('nope', 500)]);

        $email = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        $this->assertSame(Email::STATUS_SENT, $email->fresh()->status);
    }

    public function test_manual_resend_notifies_the_webhook(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');

        $email = Email::create([
            'recipient' => 'a@b.it',
            'subject' => 'S',
            'body' => 'B',
            'status' => Email::STATUS_FAILED,
            'last_error' => 'vecchio errore',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson("/emails/{$email->id}/send")
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request['uuid'] === $email->uuid
            && $request['status'] === Email::STATUS_SENT
            && $request['success'] === true);
    }

    public function test_sync_api_send_notifies_the_webhook_of_the_request(): void
    {
        Setting::set('webhook_url', 'https://default.example.com/hook');

        ApiKey::create(['name' => 'default', 'key' => 'test-api-key']);

        $this->postJson('/api/send', [
            'to' => ['a@b.it', 'c@d.it'],
            'subject' => 'S',
            'body' => 'B',
            'webhook' => 'https://specifico.example.com/hook',
            'sync' => true,
        ], ['X-API-KEY' => 'test-api-key'])->assertOk();

        // Una notifica per destinatario, tutte al webhook della richiesta.
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://default.example.com/hook');
    }
}
