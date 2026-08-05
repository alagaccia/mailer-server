<?php

namespace App\Services;

use App\Contracts\BridgeMailer;
use App\Mail\BridgeEmail;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Throwable;

class MailerService implements BridgeMailer
{
    protected const MAILER_NAME = 'smtp_runtime';

    /** @var array<string, mixed>|null */
    protected ?array $activeConfig = null;

    /**
     * Configura il mailer runtime leggendo le impostazioni dal database
     * (oppure dalla configurazione passata, es. "invia email di prova").
     *
     * @param  array<string, mixed>|null  $override
     */
    public function configure(?array $override = null): void
    {
        $config = $override ?? static::settingsConfig();

        config([
            'mail.mailers.'.self::MAILER_NAME => static::toMailerConfig($config),
            'mail.from' => [
                'address' => $config['from_address'] ?: ($config['username'] ?: 'noreply@localhost'),
                'name' => $config['from_name'] ?: config('app.name'),
            ],
        ]);

        // Scarta l'eventuale istanza già risolta così le impostazioni
        // appena salvate vengono sempre rilette.
        Mail::purge(self::MAILER_NAME);

        $this->activeConfig = $config;
    }

    public function send(Email $email): true|string
    {
        try {
            if ($this->activeConfig === null) {
                $this->configure();
            }

            Mail::mailer(self::MAILER_NAME)
                ->to($email->recipient)
                ->send(new BridgeEmail(
                    emailSubject: $email->subject,
                    htmlBody: $email->body,
                    emailAttachments: $email->attachments ?? [],
                    replyToAddress: $this->activeConfig['reply_to'] ?? null,
                ));

            return true;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function testConnection(array $config): true|string
    {
        try {
            $transport = (new EsmtpTransportFactory)->create(new Dsn(
                ($config['encryption'] ?? 'ssl') === 'ssl' ? 'smtps' : 'smtp',
                (string) $config['host'],
                ($config['username'] ?? null) ?: null,
                ($config['password'] ?? null) ?: null,
                (int) $config['port'],
                ($config['encryption'] ?? 'ssl') === 'none' ? ['auto_tls' => false] : [],
            ));

            $transport->getStream()->setTimeout(10);
            $transport->start();
            $transport->stop();

            return true;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function sendTest(array $config, string $to): true|string
    {
        try {
            $this->configure($config);

            Mail::mailer(self::MAILER_NAME)
                ->to($to)
                ->send(new BridgeEmail(
                    emailSubject: 'Email di prova — Mail Bridge',
                    htmlBody: '<h1>Mail Bridge</h1><p>Se stai leggendo questa email la configurazione SMTP funziona correttamente.</p>',
                    replyToAddress: ($config['reply_to'] ?? null) ?: null,
                ));

            return true;
        } catch (Throwable $e) {
            return $e->getMessage();
        } finally {
            // Non lasciare la configurazione di prova attiva per invii successivi.
            $this->activeConfig = null;
        }
    }

    /**
     * Configurazione SMTP corrente letta dalla tabella settings.
     *
     * @return array<string, mixed>
     */
    public static function settingsConfig(): array
    {
        return [
            'host' => Setting::get('smtp_host', ''),
            'port' => (int) Setting::get('smtp_port', '465'),
            'username' => Setting::get('smtp_username'),
            'password' => Setting::get('smtp_password'),
            'encryption' => Setting::get('smtp_encryption', 'ssl'),
            'from_name' => Setting::get('smtp_from_name'),
            'from_address' => Setting::get('smtp_from_address'),
            'reply_to' => Setting::get('smtp_reply_to'),
        ];
    }

    /**
     * Traduce la configurazione del wizard/settings nel formato dei mailer Laravel.
     *
     * Semantica encryption: "ssl" = TLS implicito (porta 465, scheme smtps),
     * "tls" = STARTTLS negoziato automaticamente (porta 587),
     * "none" = nessuna cifratura (auto_tls disattivato).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected static function toMailerConfig(array $config): array
    {
        $mailer = [
            'transport' => 'smtp',
            'host' => $config['host'],
            'port' => (int) $config['port'],
            'username' => ($config['username'] ?? null) ?: null,
            'password' => ($config['password'] ?? null) ?: null,
            'timeout' => 15,
        ];

        if (($config['encryption'] ?? 'ssl') === 'ssl') {
            $mailer['scheme'] = 'smtps';
        } elseif (($config['encryption'] ?? 'ssl') === 'none') {
            $mailer['scheme'] = 'smtp';
            $mailer['auto_tls'] = false;
        }

        return $mailer;
    }
}
