<?php

namespace App\Console\Commands;

use App\Contracts\BridgeMailer;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMailQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:process-queue {--batch=50 : Numero massimo di email per esecuzione}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invia le email in coda (stato pending)';

    public function handle(BridgeMailer $mailer): int
    {
        // Righe rimaste in "sending" per un worker interrotto: tornano failed
        // così non restano bloccate per sempre (re-inviabili dalla dashboard).
        Email::where('status', Email::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'status' => Email::STATUS_FAILED,
                'last_error' => 'Invio interrotto (timeout del worker)',
            ]);

        if (Setting::get('mailer_enabled', '1') !== '1') {
            $this->info('Mailer disabilitato dalle impostazioni: nessun invio.');

            return self::SUCCESS;
        }

        $batch = max(1, (int) $this->option('batch'));

        // Claim atomico del batch: evita il doppio invio con worker concorrenti.
        $ids = DB::transaction(function () use ($batch) {
            $ids = Email::pending()
                ->orderBy('id')
                ->limit($batch)
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                Email::whereIn('id', $ids)->update(['status' => Email::STATUS_SENDING]);
            }

            return $ids;
        });

        if ($ids->isEmpty()) {
            $this->info('Nessuna email in coda.');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach (Email::whereIn('id', $ids)->orderBy('id')->get() as $email) {
            $result = $mailer->send($email);

            if ($result === true) {
                $email->update([
                    'status' => Email::STATUS_SENT,
                    'sent_at' => now(),
                    'attempts' => $email->attempts + 1,
                    'last_error' => null,
                ]);
                $sent++;
            } else {
                $email->update([
                    'status' => Email::STATUS_FAILED,
                    'last_error' => $result,
                    'attempts' => $email->attempts + 1,
                ]);
                $failed++;

                Log::warning("Invio email #{$email->id} a {$email->recipient} fallito: {$result}");
            }
        }

        $this->info("Coda processata. Inviate: {$sent}, fallite: {$failed}.");

        return self::SUCCESS;
    }
}
