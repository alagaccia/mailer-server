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
        $data = $request->validate($this->rules());

        Setting::set('webhook_url', ($data['url'] ?? '') !== '' ? $data['url'] : null);
        Setting::set('webhook_token', ($data['token'] ?? '') !== '' ? $data['token'] : null);
        Setting::set('webhook_secret', ($data['secret'] ?? '') !== '' ? $data['secret'] : null);
        Setting::set('webhook_signature_header', trim($data['signature_header'] ?? '') !== '' ? trim($data['signature_header']) : null);

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
            'signature_header' => self::SIGNATURE_HEADER_RULES,
        ]);

        $result = $webhook->test(
            $data['url'],
            $data['token'] ?? null,
            $data['secret'] ?? null,
            $data['signature_header'] ?? null,
        );

        return response()->json($result === true ? ['ok' => true] : ['ok' => false, 'error' => $result]);
    }

    /**
     * Il nome dell'intestazione deve essere un token HTTP valido.
     *
     * @var array<int, string>
     */
    protected const SIGNATURE_HEADER_RULES = ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9!#$%&\'*+\-.^_`|~]+$/'];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        // URL vuoto = webhook di default disattivato.
        // Token vuoto = chiamata senza intestazioni di autenticazione.
        // Secret vuoto = nessuna firma HMAC allegata alla notifica.
        return [
            'url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'token' => ['nullable', 'string', 'max:1024'],
            'secret' => ['nullable', 'string', 'max:1024'],
            'signature_header' => self::SIGNATURE_HEADER_RULES,
        ];
    }
}
