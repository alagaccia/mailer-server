<?php

namespace App\Http\Controllers;

use App\Contracts\BridgeMailer;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class EmailController extends Controller
{
    /**
     * Dettaglio email per la modale di anteprima (body e allegati completi).
     */
    public function show(int $id): JsonResponse
    {
        $email = Email::find($id);

        if ($email === null) {
            return response()->json(['error' => 'Email not found'], 404);
        }

        return response()->json([
            'id' => $email->id,
            'uuid' => $email->uuid,
            'recipient' => $email->recipient,
            'subject' => $email->subject,
            'body' => $email->body,
            'status' => $email->status,
            'attachments' => $email->attachments ?? [],
            'last_error' => $email->last_error,
            'created_at' => $email->created_at?->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Invio manuale (o re-invio) dalla dashboard.
     */
    public function send(int $id, BridgeMailer $mailer): JsonResponse
    {
        $email = Email::find($id);

        if ($email === null) {
            return response()->json(['error' => 'Email not found'], 404);
        }

        if ($email->status === Email::STATUS_SENT) {
            return response()->json(['error' => 'Email already sent'], 409);
        }

        if (Setting::get('mailer_enabled', '1') !== '1') {
            return response()->json(['error' => 'Mailer disabilitato dalle impostazioni'], 403);
        }

        $email->update(['status' => Email::STATUS_SENDING]);

        $result = $mailer->send($email);

        if ($result === true) {
            $email->update([
                'status' => Email::STATUS_SENT,
                'sent_at' => now(),
                'attempts' => $email->attempts + 1,
                'last_error' => null,
            ]);

            return response()->json([
                'message' => 'Email sent successfully',
                'id' => $email->id,
                'recipient' => $email->recipient,
            ]);
        }

        $email->update([
            'status' => Email::STATUS_FAILED,
            'last_error' => $result,
            'attempts' => $email->attempts + 1,
        ]);

        return response()->json([
            'error' => 'Failed to send email',
            'id' => $email->id,
            'details' => $result,
        ], 500);
    }
}
