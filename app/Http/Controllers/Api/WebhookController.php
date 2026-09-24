<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/webhook — le stesse impostazioni di Impostazioni → Webhook, ma via
 * API: le scrive `php artisan mailer-transport:install` dell'applicazione
 * che usa questo mailer, autenticandosi con la sua chiave API.
 *
 * Token e segreto non vengono mai restituiti in chiaro.
 */
class WebhookController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->describe());
    }

    /**
     * Sostituisce le impostazioni: i campi assenti vengono azzerati, come
     * salvando il modulo del pannello con quei campi vuoti.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(WebhookService::settingsRules());

        WebhookService::saveSettings($data);

        return response()->json(['message' => 'Impostazioni webhook salvate', ...$this->describe()]);
    }

    /**
     * @return array{url: string|null, has_token: bool, has_secret: bool, signature_header: string}
     */
    protected function describe(): array
    {
        return [
            'url' => Setting::get('webhook_url'),
            'has_token' => (Setting::get('webhook_token') ?? '') !== '',
            'has_secret' => (Setting::get('webhook_secret') ?? '') !== '',
            'signature_header' => WebhookService::resolveSignatureHeader(),
        ];
    }
}
