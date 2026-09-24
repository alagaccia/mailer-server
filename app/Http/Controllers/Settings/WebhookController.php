<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\WebhookNotifier;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    /**
     * Pagina impostazioni webhook (solo admin).
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Webhook', [
            'webhook' => [
                'url' => Setting::get('webhook_url') ?? '',
                'token' => Setting::get('webhook_token') ?? '',
                'secret' => Setting::get('webhook_secret') ?? '',
                'signature_header' => Setting::get('webhook_signature_header') ?? '',
            ],
            'defaultSignatureHeader' => WebhookService::DEFAULT_SIGNATURE_HEADER,
            'signatureAlgo' => WebhookService::SIGNATURE_ALGO,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(WebhookService::settingsRules());

        WebhookService::saveSettings($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Impostazioni webhook salvate.']);

        return back();
    }

    /**
     * Notifica di prova all'URL del form (senza salvarlo).
     */
    public function test(Request $request, WebhookNotifier $webhook): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'token' => ['nullable', 'string', 'max:1024'],
            'secret' => ['nullable', 'string', 'max:1024'],
            'signature_header' => WebhookService::settingsRules()['signature_header'],
        ]);

        $result = $webhook->test(
            $data['url'],
            $data['token'] ?? null,
            $data['secret'] ?? null,
            $data['signature_header'] ?? null,
        );

        return response()->json($result === true ? ['ok' => true] : ['ok' => false, 'error' => $result]);
    }
}
