<?php

namespace Tests\Feature;

use App\Contracts\BridgeMailer;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeBridgeMailer;
use Tests\TestCase;

class ProcessMailQueueTest extends TestCase
{
    use RefreshDatabase;

    protected FakeBridgeMailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = new FakeBridgeMailer;
        $this->app->instance(BridgeMailer::class, $this->mailer);

        Setting::set('mailer_enabled', '1');
    }

    public function test_pending_emails_are_sent_and_marked(): void
    {
        $ok = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);
        $ko = Email::create(['recipient' => 'c@d.it', 'subject' => 'S', 'body' => 'B']);

        $this->mailer->resultsById = [$ko->id => 'SMTP timeout'];

        $this->artisan('mail:process-queue')->assertSuccessful();

        $ok->refresh();
        $ko->refresh();

        $this->assertSame(Email::STATUS_SENT, $ok->status);
        $this->assertNotNull($ok->sent_at);
        $this->assertSame(1, $ok->attempts);

        $this->assertSame(Email::STATUS_FAILED, $ko->status);
        $this->assertSame('SMTP timeout', $ko->last_error);
        $this->assertSame(1, $ko->attempts);
    }

    public function test_non_pending_rows_are_not_claimed(): void
    {
        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B', 'status' => Email::STATUS_SENT]);
        Email::create(['recipient' => 'c@d.it', 'subject' => 'S', 'body' => 'B', 'status' => Email::STATUS_FAILED]);

        $this->artisan('mail:process-queue')->assertSuccessful();

        $this->assertSame([], $this->mailer->sentIds);
    }

    public function test_disabled_mailer_skips_processing(): void
    {
        Setting::set('mailer_enabled', '0');

        Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B']);

        $this->artisan('mail:process-queue')->assertSuccessful();

        $this->assertSame([], $this->mailer->sentIds);
        $this->assertSame(1, Email::pending()->count());
    }

    public function test_stale_sending_rows_are_swept_to_failed(): void
    {
        $stale = Email::create(['recipient' => 'a@b.it', 'subject' => 'S', 'body' => 'B', 'status' => Email::STATUS_SENDING]);
        Email::where('id', $stale->id)->update(['updated_at' => now()->subMinutes(20)]);

        $fresh = Email::create(['recipient' => 'c@d.it', 'subject' => 'S', 'body' => 'B', 'status' => Email::STATUS_SENDING]);

        $this->artisan('mail:process-queue')->assertSuccessful();

        $this->assertSame(Email::STATUS_FAILED, $stale->refresh()->status);
        $this->assertSame('Invio interrotto (timeout del worker)', $stale->last_error);

        // Le righe "sending" recenti non vengono toccate.
        $this->assertSame(Email::STATUS_SENDING, $fresh->refresh()->status);
    }

    public function test_batch_option_limits_claimed_rows(): void
    {
        foreach (range(1, 3) as $i) {
            Email::create(['recipient' => "u{$i}@b.it", 'subject' => 'S', 'body' => 'B']);
        }

        $this->artisan('mail:process-queue', ['--batch' => 2])->assertSuccessful();

        $this->assertCount(2, $this->mailer->sentIds);
        $this->assertSame(1, Email::pending()->count());
    }
}
