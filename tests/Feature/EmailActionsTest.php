<?php

namespace Tests\Feature;

use App\Contracts\BridgeMailer;
use App\Models\Email;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeBridgeMailer;
use Tests\TestCase;

class EmailActionsTest extends TestCase
{
    use RefreshDatabase;

    protected FakeBridgeMailer $mailer;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = new FakeBridgeMailer;
        $this->app->instance(BridgeMailer::class, $this->mailer);

        Setting::set('mailer_enabled', '1');

        $this->user = User::factory()->create();
    }

    public function test_guests_cannot_use_email_actions(): void
    {
        $email = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->get("/emails/{$email->id}")->assertRedirect('/login');
        $this->post("/emails/{$email->id}/send")->assertRedirect('/login');
    }

    public function test_show_returns_email_detail(): void
    {
        $email = Email::create([
            'recipient' => 'a@b.it',
            'subject' => 'Oggetto',
            'body' => '<h1>Ciao</h1>',
            'attachments' => [['filename' => 't.txt', 'content' => base64_encode('x'), 'mime' => 'text/plain']],
        ]);

        $this->actingAs($this->user)
            ->getJson("/emails/{$email->id}")
            ->assertOk()
            ->assertJson([
                'id' => $email->id,
                'recipient' => 'a@b.it',
                'subject' => 'Oggetto',
                'body' => '<h1>Ciao</h1>',
                'status' => 'pending',
            ])
            ->assertJsonPath('attachments.0.filename', 't.txt');
    }

    public function test_show_returns_404_for_missing_email(): void
    {
        $this->actingAs($this->user)
            ->getJson('/emails/999')
            ->assertNotFound()
            ->assertExactJson(['error' => 'Email not found']);
    }

    public function test_manual_send_succeeds(): void
    {
        $email = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B', 'status' => Email::STATUS_FAILED]);

        $this->actingAs($this->user)
            ->postJson("/emails/{$email->id}/send")
            ->assertOk()
            ->assertJson([
                'message' => 'Email sent successfully',
                'id' => $email->id,
                'recipient' => 'a@b.it',
            ]);

        $email->refresh();
        $this->assertSame(Email::STATUS_SENT, $email->status);
        $this->assertNotNull($email->sent_at);
    }

    public function test_manual_send_conflicts_when_already_sent(): void
    {
        $email = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B', 'status' => Email::STATUS_SENT]);

        $this->actingAs($this->user)
            ->postJson("/emails/{$email->id}/send")
            ->assertStatus(409)
            ->assertExactJson(['error' => 'Email already sent']);
    }

    public function test_manual_send_blocked_when_mailer_disabled(): void
    {
        Setting::set('mailer_enabled', '0');

        $email = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->actingAs($this->user)
            ->postJson("/emails/{$email->id}/send")
            ->assertStatus(403)
            ->assertExactJson(['error' => 'Mailer disabilitato dalle impostazioni']);
    }

    public function test_manual_send_failure_returns_500_and_marks_failed(): void
    {
        $this->mailer->result = 'connessione rifiutata';

        $email = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->actingAs($this->user)
            ->postJson("/emails/{$email->id}/send")
            ->assertStatus(500)
            ->assertJson(['error' => 'Failed to send email', 'details' => 'connessione rifiutata']);

        $this->assertSame(Email::STATUS_FAILED, $email->refresh()->status);
    }

    public function test_mailer_toggle_flips_setting(): void
    {
        $this->actingAs($this->user)
            ->postJson('/mailer/toggle')
            ->assertOk()
            ->assertExactJson(['enabled' => '0']);

        $this->assertSame('0', Setting::get('mailer_enabled'));

        $this->actingAs($this->user)
            ->postJson('/mailer/toggle')
            ->assertOk()
            ->assertExactJson(['enabled' => '1']);
    }

    public function test_dashboard_renders_with_stats_and_filters(): void
    {
        Email::create(['recipient' => 'a@b.it', 'subject' => 'Benvenuto', 'body' => 'B', 'status' => Email::STATUS_SENT]);
        Email::create(['recipient' => 'c@d.it', 'subject' => 'Altro', 'body' => 'B']);

        $this->actingAs($this->user)
            ->get('/dashboard?filter_subject=Benvenuto')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.total', 2)
                ->where('stats.sent', 1)
                ->where('stats.pending', 1)
                ->where('emails.total', 1)
                ->where('filters.filter_subject', 'Benvenuto'),
            );
    }
}
