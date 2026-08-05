<?php

namespace Tests\Feature\Api;

use App\Contracts\BridgeMailer;
use App\Models\ApiKey;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeBridgeMailer;
use Tests\TestCase;

class SendEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected FakeBridgeMailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = new FakeBridgeMailer;
        $this->app->instance(BridgeMailer::class, $this->mailer);

        ApiKey::create(['name' => 'default', 'key' => 'test-api-key']);
        Setting::set('mailer_enabled', '1');
    }

    /**
     * @param  array<string, mixed>|string  $payload
     */
    protected function send(array|string $payload, ?string $apiKey = 'test-api-key'): TestResponse
    {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

        if ($apiKey !== null) {
            $headers['X-API-KEY'] = $apiKey;
        }

        return $this->call(
            'POST',
            '/api/send',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            is_string($payload) ? $payload : json_encode($payload),
        );
    }

    public function test_missing_api_key_returns_401(): void
    {
        $this->send(['to' => 'a@b.it'], null)
            ->assertStatus(401)
            ->assertExactJson(['error' => 'Unauthorized']);
    }

    public function test_wrong_api_key_returns_401(): void
    {
        $this->send(['to' => 'a@b.it'], 'wrong-key')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'Unauthorized']);
    }

    public function test_any_stored_api_key_is_accepted(): void
    {
        ApiKey::create(['name' => 'sito-vetrina', 'key' => 'seconda-chiave']);

        $this->send([
            'to' => 'a@b.it',
            'subject' => 'Oggetto',
            'body' => '<p>Corpo</p>',
        ], 'seconda-chiave')->assertStatus(201);
    }

    public function test_bearer_token_is_accepted(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer test-api-key',
        ];

        $this->call(
            'POST',
            '/api/send',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            json_encode(['to' => 'a@b.it', 'subject' => 'Oggetto', 'body' => '<p>Corpo</p>']),
        )->assertStatus(201);
    }

    public function test_get_method_returns_405_with_legacy_shape(): void
    {
        $this->getJson('/api/send')
            ->assertStatus(405)
            ->assertExactJson(['error' => 'Method Not Allowed. Use POST.']);
    }

    public function test_not_installed_returns_503(): void
    {
        $this->markNotInstalled();

        $this->send(['to' => 'a@b.it'])
            ->assertStatus(503)
            ->assertExactJson(['error' => 'Application not installed']);
    }

    public function test_malformed_json_returns_400(): void
    {
        $this->send('{invalid json')
            ->assertStatus(400)
            ->assertJson(['error' => 'Malformed JSON']);
    }

    public function test_empty_body_returns_400(): void
    {
        // Body vuoto: json_decode fallisce, stessa risposta della vecchia app.
        $this->send('')
            ->assertStatus(400)
            ->assertJson(['error' => 'Malformed JSON']);
    }

    public function test_valid_but_empty_json_returns_400(): void
    {
        $this->send('null')
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid JSON body']);

        $this->send('{}')
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid JSON body']);
    }

    public function test_disabled_mailer_returns_403(): void
    {
        Setting::set('mailer_enabled', '0');

        $this->send(['to' => 'a@b.it', 'subject' => 'S', 'body' => 'B'])
            ->assertStatus(403)
            ->assertExactJson(['error' => 'Mailer disabilitato dalle impostazioni']);
    }

    public function test_missing_field_returns_400_with_field_name(): void
    {
        $this->send(['subject' => 'S', 'body' => 'B'])
            ->assertStatus(400)
            ->assertExactJson(['error' => 'Missing fields', 'field' => 'to']);

        $this->send(['to' => 'a@b.it', 'body' => 'B'])
            ->assertStatus(400)
            ->assertExactJson(['error' => 'Missing fields', 'field' => 'subject']);

        $this->send(['to' => 'a@b.it', 'subject' => 'S'])
            ->assertStatus(400)
            ->assertExactJson(['error' => 'Missing fields', 'field' => 'body']);
    }

    public function test_invalid_email_returns_400_with_address(): void
    {
        $this->send(['to' => ['a@b.it', 'not-an-email'], 'subject' => 'S', 'body' => 'B'])
            ->assertStatus(400)
            ->assertExactJson(['error' => 'Invalid email address', 'email' => 'not-an-email']);

        $this->assertSame(0, Email::count());
    }

    public function test_invalid_webhook_returns_400(): void
    {
        $this->send(['to' => 'a@b.it', 'subject' => 'S', 'body' => 'B', 'webhook' => 'non-un-url'])
            ->assertStatus(400)
            ->assertExactJson(['error' => 'Invalid webhook', 'webhook' => 'non-un-url']);

        // Anche gli schemi diversi da http/https vengono rifiutati.
        $this->send(['to' => 'a@b.it', 'subject' => 'S', 'body' => 'B', 'webhook' => 'ftp://esempio.it/hook'])
            ->assertStatus(400)
            ->assertExactJson(['error' => 'Invalid webhook', 'webhook' => 'ftp://esempio.it/hook']);

        $this->assertSame(0, Email::count());
    }

    public function test_webhook_is_stored_on_every_queued_row(): void
    {
        $this->send([
            'to' => ['a@b.it', 'c@d.it'],
            'subject' => 'S',
            'body' => 'B',
            'webhook' => 'https://tuo-software.it/hooks/mail-bridge',
        ])->assertStatus(201);

        $this->assertSame(
            2,
            Email::where('webhook', 'https://tuo-software.it/hooks/mail-bridge')->count(),
        );
    }

    public function test_webhook_is_null_when_not_provided(): void
    {
        $this->send(['to' => 'a@b.it', 'subject' => 'S', 'body' => 'B'])->assertStatus(201);

        $this->assertNull(Email::first()->webhook);
    }

    public function test_async_send_queues_one_row_per_recipient(): void
    {
        $response = $this->send([
            'to' => ['a@b.it', 'c@d.it'],
            'subject' => 'Oggetto',
            'body' => '<h1>Ciao</h1>',
            'attachments' => [
                ['filename' => 't.txt', 'content' => base64_encode('hello'), 'mime' => 'text/plain'],
            ],
        ]);

        $response->assertStatus(201)->assertJson(['message' => 'Queued', 'recipients' => 2]);

        $ids = $response->json('ids');
        $this->assertCount(2, $ids);
        $this->assertContainsOnly('string', $ids);

        $this->assertSame(2, Email::pending()->count());
        $this->assertSame([], $this->mailer->sentIds);

        $email = Email::first();
        $this->assertSame('a@b.it', $email->recipient);
        $this->assertSame('t.txt', $email->attachments[0]['filename']);
    }

    public function test_sync_send_persists_rows_and_sends_inline(): void
    {
        $response = $this->send([
            'to' => ['a@b.it', 'c@d.it'],
            'subject' => 'Oggetto',
            'body' => '<h1>Ciao</h1>',
            'sync' => true,
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Sent',
            'sent' => ['a@b.it', 'c@d.it'],
            'failed' => [],
        ]);

        $this->assertSame(2, Email::where('status', Email::STATUS_SENT)->count());
        $this->assertCount(2, $this->mailer->sentIds);
    }

    public function test_sync_send_reports_partial_failures(): void
    {
        $this->mailer->result = true;

        // Il secondo destinatario fallisce.
        $this->mailer->resultsById = [2 => 'SMTP connect failed'];

        $response = $this->send([
            'to' => ['a@b.it', 'c@d.it'],
            'subject' => 'Oggetto',
            'body' => 'B',
            'sync' => '1',
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Sent',
            'sent' => ['a@b.it'],
            'failed' => [['email' => 'c@d.it', 'error' => 'SMTP connect failed']],
        ]);

        $failed = Email::where('status', Email::STATUS_FAILED)->first();
        $this->assertSame('c@d.it', $failed->recipient);
        $this->assertSame('SMTP connect failed', $failed->last_error);
        $this->assertSame(1, $failed->attempts);
    }

    public function test_sync_send_returns_500_when_all_fail(): void
    {
        $this->mailer->result = 'kaboom';

        $this->send(['to' => 'a@b.it', 'subject' => 'S', 'body' => 'B', 'sync' => true])
            ->assertStatus(500)
            ->assertJson([
                'message' => 'All emails failed',
                'failed' => [['email' => 'a@b.it', 'error' => 'kaboom']],
            ]);
    }
}
