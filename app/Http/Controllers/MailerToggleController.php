<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class MailerToggleController extends Controller
{
    /**
     * Attiva/ferma l'invio email (kill switch globale).
     */
    public function __invoke(): JsonResponse
    {
        $enabled = Setting::get('mailer_enabled', '1') === '1';

        Setting::set('mailer_enabled', $enabled ? '0' : '1');

        return response()->json(['enabled' => $enabled ? '0' : '1']);
    }
}
