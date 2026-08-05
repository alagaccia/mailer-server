<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BridgeMailer;
use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * POST /api/send — contratto JSON ereditato dalla vecchia app "mailer":
 * shape delle risposte e ordine dei controlli vanno preservati.
 */
class SendController extends Controller
{
    public function __invoke(Request $request, BridgeMailer $mailer): JsonResponse
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

        $subject = (string) $data['subject'];
        $body = (string) $data['body'];
        $attachments = is_array($data['attachments'] ?? null) ? array_values($data['attachments']) : [];
        $sync = filter_var($data['sync'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $sync
            ? $this->sendSync($recipients, $subject, $body, $attachments, $mailer)
            : $this->queue($recipients, $subject, $body, $attachments);
    }

    /**
     * Accoda una riga per destinatario; il cron la processa entro un minuto.
     *
     * @param  list<string>  $recipients
     * @param  array<int, array<string, string>>  $attachments
     */
    protected function queue(array $recipients, string $subject, string $body, array $attachments): JsonResponse
    {
        try {
            $ids = [];

            foreach ($recipients as $recipient) {
                $email = Email::create([
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'body' => $body,
                    'attachments' => $attachments ?: null,
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
     */
    protected function sendSync(array $recipients, string $subject, string $body, array $attachments, BridgeMailer $mailer): JsonResponse
    {
        $sent = [];
        $failed = [];

        try {
            foreach ($recipients as $recipient) {
                $email = Email::create([
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'body' => $body,
                    'attachments' => $attachments ?: null,
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
