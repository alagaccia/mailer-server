<?php

namespace App\Services;

use App\Contracts\WebhookNotifier;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookService implements WebhookNotifier
{
    /**
     * Secondi di attesa massima per la risposta del webhook: la chiamata è
     * sincrona rispetto all'invio, quindi va tenuta corta.
     */
    protected const TIMEOUT = 10;

    /**
     * Algoritmo della firma HMAC e intestazione usata quando non ne viene
     * configurata una personalizzata.
     */
    public const SIGNATURE_ALGO = 'sha256';

    public const DEFAULT_SIGNATURE_HEADER = 'X-Signature';

    /**
     * Il nome dell'intestazione deve essere un token HTTP valido.
     */
    public const SIGNATURE_HEADER_PATTERN = '/^[A-Za-z0-9!#$%&\'*+\-.^_`|~]+$/';

    public function notify(Email $email): true|string|null
    {
        $url = static::resolveUrl($email);

        if ($url === null) {
            return null;
        }

        return $this->post(
            $url,
            static::payload($email),
            $email->webhook_token,
            $email->webhook_secret,
            $email->webhook_signature_header,
        );
    }

    public function test(string $url, ?string $token = null, ?string $secret = null, ?string $header = null): true|string
    {
        return $this->post($url, [
            'event' => 'webhook.test',
            'uuid' => null,
            'recipient' => 'destinatario@example.com',
            'subject' => 'Notifica di prova — Mail Bridge',
            'status' => Email::STATUS_SENT,
            'success' => true,
            'attempts' => 1,
            'error' => null,
            'sent_at' => now()->toIso8601String(),
            'created_at' => now()->toIso8601String(),
        ], $token, $secret, $header);
    }

    /**
     * Webhook da chiamare per questa email: quello della riga ha la
     * precedenza su quello di default delle impostazioni. Lo stesso vale per
     * token, segreto e intestazione della firma arrivati con la richiesta.
     */
    public static function resolveUrl(Email $email): ?string
    {
        $url = $email->webhook ?: Setting::get('webhook_url');

        return ($url ?? '') !== '' ? $url : null;
    }

    /**
     * Token di autenticazione da allegare alla chiamata, se configurato.
     */
    public static function resolveToken(): ?string
    {
        $token = Setting::get('webhook_token');

        return ($token ?? '') !== '' ? $token : null;
    }

    /**
     * Chiave segreta con cui firmare il corpo della notifica, se configurata.
     */
    public static function resolveSecret(): ?string
    {
        $secret = Setting::get('webhook_secret');

        return ($secret ?? '') !== '' ? $secret : null;
    }

    /**
     * Intestazione in cui viaggia la firma: personalizzabile, con un default
     * se non ne è stata configurata una.
     */
    public static function resolveSignatureHeader(): string
    {
        $header = trim((string) Setting::get('webhook_signature_header'));

        return $header !== '' ? $header : self::DEFAULT_SIGNATURE_HEADER;
    }

    /**
     * Un URL è utilizzabile come webhook solo se http/https e ben formato.
     */
    public static function isValidUrl(mixed $url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * Corpo della notifica: stato finale dell'elaborazione dell'email.
     *
     * @return array<string, mixed>
     */
    protected static function payload(Email $email): array
    {
        return [
            'event' => 'email.processed',
            'uuid' => $email->uuid,
            'recipient' => $email->recipient,
            'subject' => $email->subject,
            'status' => $email->status,
            'success' => $email->status === Email::STATUS_SENT,
            'attempts' => $email->attempts,
            'error' => $email->last_error,
            'sent_at' => $email->sent_at?->toIso8601String(),
            'created_at' => $email->created_at?->toIso8601String(),
        ];
    }

    /**
     * Lo stesso token viene inviato in entrambe le forme, così il ricevente
     * può validarlo come api key o come bearer token indifferentemente.
     *
     * @return array<string, string>
     */
    protected static function authHeaders(?string $token): array
    {
        if (($token ?? '') === '') {
            return [];
        }

        return [
            'X-API-KEY' => $token,
            'Authorization' => "Bearer {$token}",
        ];
    }

    /**
     * Firma HMAC del corpo grezzo della notifica, nell'intestazione scelta.
     * Senza chiave segreta non viene aggiunta nessuna firma.
     *
     * @return array<string, string>
     */
    protected static function signatureHeaders(string $body, ?string $secret, string $header): array
    {
        if (($secret ?? '') === '') {
            return [];
        }

        $signature = hash_hmac(self::SIGNATURE_ALGO, $body, $secret);

        return [$header => self::SIGNATURE_ALGO.'='.$signature];
    }

    /**
     * Il webhook non deve mai far fallire l'invio: gli errori vengono
     * registrati nel log e restituiti al chiamante, non sollevati.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function post(string $url, array $payload, ?string $token = null, ?string $secret = null, ?string $header = null): true|string
    {
        if (! static::isValidUrl($url)) {
            return 'URL del webhook non valido';
        }

        $token = ($token ?? '') !== '' ? $token : static::resolveToken();
        $secret = ($secret ?? '') !== '' ? $secret : static::resolveSecret();
        $header = trim((string) $header) !== '' ? trim((string) $header) : static::resolveSignatureHeader();

        // Il corpo viene serializzato qui e spedito così com'è: la firma deve
        // coprire esattamente i byte che il ricevente si ritrova da validare.
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->acceptJson()
                ->withHeaders(static::authHeaders($token))
                ->withHeaders(static::signatureHeaders($body, $secret, $header))
                ->withBody($body, 'application/json')
                ->post($url);

            if ($response->successful()) {
                return true;
            }

            $error = "Il webhook ha risposto {$response->status()}";
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        Log::warning("Notifica webhook a {$url} fallita: {$error}");

        return $error;
    }
}
