<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BridgeMailer;
use App\Contracts\WebhookNotifier;
use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\Setting;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * POST /api/send — contratto JSON ereditato dalla vecchia app "mailer":
 * shape delle risposte e ordine dei controlli vanno preservati.
 */
class SendController extends Controller
{
    public function __invoke(Request $request, BridgeMailer $mailer, WebhookNotifier $notifier): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Malformed JSON', 'details' => json_last_error_msg()], 400);
        }

        if (empty($data) || ! is_array($data)) {
            return response()->json(['error' => 'Invalid JSON body'], 400);
        }

        if (Setting::get('mailer_enabled', '1') !== '1') {
            return response()->json(['error' => 'Mailer disabilitato dalle impostazioni'], 403);
        }

        foreach (['to', 'subject', 'body'] as $field) {
            $value = $data[$field] ?? null;

            if (empty($value) || ($field !== 'to' && ! is_scalar($value))) {
                return response()->json(['error' => 'Missing fields', 'field' => $field], 400);
            }
        }

        $recipients = is_array($data['to']) ? array_values($data['to']) : [$data['to']];

        foreach ($recipients as $recipient) {
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'error' => 'Invalid email address',
                    'email' => is_scalar($recipient) ? (string) $recipient : '',
                ], 400);
            }
        }

        $uuid = $data['uuid'] ?? null;

        if ($uuid !== null && ! (is_string($uuid) && Str::isUuid($uuid))) {
            return response()->json([
                'error' => 'Invalid uuid',
                'uuid' => is_scalar($uuid) ? (string) $uuid : '',
            ], 400);
        }

        // Webhook della singola richiesta: sostituisce quello di default
        // delle impostazioni per le email create qui.
        $webhookUrl = $data['webhook'] ?? null;

        if ($webhookUrl !== null && ! WebhookService::isValidUrl($webhookUrl)) {
            return response()->json([
                'error' => 'Invalid webhook',
                'webhook' => is_scalar($webhookUrl) ? (string) $webhookUrl : '',
            ], 400);
        }

        // Credenziali con cui chiamare quel webhook: viaggiano con la
        // richiesta cosi' il mittente non deve configurare nulla qui.
        $webhookCredentials = [];

        foreach (['webhook_token', 'webhook_secret', 'webhook_signature_header'] as $field) {
            $value = $data[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $valid = is_string($value) && ($field === 'webhook_signature_header'
                ? preg_match(WebhookService::SIGNATURE_HEADER_PATTERN, $value) === 1 && strlen($value) <= 128
                : strlen($value) <= 1024);

            if (! $valid) {
                return response()->json([
                    'error' => 'Invalid '.$field,
                    $field => is_scalar($value) ? (string) $value : '',
                ], 400);
            }

            $webhookCredentials[$field] = $value;
        }

        $webhook = $webhookUrl === null ? [] : ['webhook' => $webhookUrl, ...$webhookCredentials];

        $subject = (string) $data['subject'];
        $body = (string) $data['body'];
        $attachments = is_array($data['attachments'] ?? null) ? array_values($data['attachments']) : [];
        $sync = filter_var($data['sync'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $sync
            ? $this->sendSync($recipients, $subject, $body, $attachments, $mailer, $notifier, $uuid, $webhook)
            : $this->queue($recipients, $subject, $body, $attachments, $uuid, $webhook);
    }

    /**
     * Accoda una riga per destinatario; il cron la processa entro un minuto.
     *
     * @param  list<string>  $recipients
     * @param  array<int, array<string, string>>  $attachments
     * @param  array<string, string>  $webhook  URL e credenziali del webhook della richiesta, vuoto = quello di default.
     */
    protected function queue(array $recipients, string $subject, string $body, array $attachments, ?string $uuid = null, array $webhook = []): JsonResponse
    {
        try {
            $ids = [];

            foreach ($recipients as $recipient) {
                $email = Email::create([
                    'uuid' => $uuid,
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'body' => $body,
                    'attachments' => $attachments ?: null,
                    ...$webhook,
                ]);

                // Id come stringhe: parità con il contratto della vecchia app.
                $ids[] = (string) $email->id;
            }

            return response()->json([
                'message' => 'Queued',
                'ids' => $ids,
                'recipients' => count($ids),
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Database error', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Invio sincrono nella richiesta; a differenza della vecchia app le
     * email vengono comunque registrate in tabella.
     *
     * @param  list<string>  $recipients
     * @param  array<int, array<string, string>>  $attachments
     * @param  array<string, string>  $webhook  URL e credenziali del webhook della richiesta, vuoto = quello di default.
     */
    protected function sendSync(array $recipients, string $subject, string $body, array $attachments, BridgeMailer $mailer, WebhookNotifier $notifier, ?string $uuid = null, array $webhook = []): JsonResponse
    {
        $sent = [];
        $failed = [];

        try {
            foreach ($recipients as $recipient) {
                $email = Email::create([
                    'uuid' => $uuid,
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'body' => $body,
                    'attachments' => $attachments ?: null,
                    ...$webhook,
                    'status' => Email::STATUS_SENDING,
                ]);

                $result = $mailer->send($email);

                if ($result === true) {
                    $email->update([
                        'status' => Email::STATUS_SENT,
                        'sent_at' => now(),
                        'attempts' => 1,
                    ]);
                    $sent[] = $recipient;
                } else {
                    $email->update([
                        'status' => Email::STATUS_FAILED,
                        'last_error' => $result,
                        'attempts' => 1,
                    ]);
                    $failed[] = ['email' => $recipient, 'error' => $result];
                }

                $notifier->notify($email);
            }
        } catch (Throwable $e) {
            return response()->json(['error' => 'Database error', 'details' => $e->getMessage()], 500);
        }

        if ($sent === []) {
            return response()->json(['message' => 'All emails failed', 'failed' => $failed], 500);
        }

        return response()->json(['message' => 'Sent', 'sent' => $sent, 'failed' => $failed]);
    }
}
