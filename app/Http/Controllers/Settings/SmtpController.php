<?php

namespace App\Http\Controllers\Settings;

use App\Contracts\BridgeMailer;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SmtpController extends Controller
{
    /**
     * Pagina impostazioni SMTP + chiave API (solo admin).
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Smtp', [
            'smtp' => [
                'host' => Setting::get('smtp_host', ''),
                'port' => (int) Setting::get('smtp_port', '465'),
                'username' => Setting::get('smtp_username') ?? '',
                'encryption' => Setting::get('smtp_encryption', 'ssl'),
                'from_name' => Setting::get('smtp_from_name') ?? '',
                'from_address' => Setting::get('smtp_from_address') ?? '',
                'reply_to' => Setting::get('smtp_reply_to') ?? '',
                'password_set' => (bool) Setting::get('smtp_password'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $smtp = $request->validate($this->rules());

        Setting::set('smtp_host', $smtp['host']);
        Setting::set('smtp_port', (string) $smtp['port']);
        Setting::set('smtp_username', $smtp['username'] ?? null);
        Setting::set('smtp_encryption', $smtp['encryption']);
        Setting::set('smtp_from_name', $smtp['from_name'] ?? null);
        Setting::set('smtp_from_address', $smtp['from_address'] ?? null);
        Setting::set('smtp_reply_to', $smtp['reply_to'] ?? null);

        // Password vuota = mantieni quella salvata.
        if (($smtp['password'] ?? '') !== '') {
            Setting::set('smtp_password', $smtp['password']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Impostazioni SMTP salvate.']);

        return back();
    }

    /**
     * Prova di connessione SMTP con i valori del form (senza salvarli).
     */
    public function test(Request $request, BridgeMailer $mailer): JsonResponse
    {
        $smtp = $this->resolveConfig($request);

        $result = $mailer->testConnection($smtp);

        return response()->json($result === true ? ['ok' => true] : ['ok' => false, 'error' => $result]);
    }

    /**
     * Invia un'email di prova con i valori del form (senza salvarli).
     */
    public function sendTest(Request $request, BridgeMailer $mailer): JsonResponse
    {
        $request->validate(['to' => ['required', 'string', 'email']]);

        $smtp = $this->resolveConfig($request);

        $result = $mailer->sendTest($smtp, $request->string('to')->toString());

        return response()->json($result === true ? ['ok' => true] : ['ok' => false, 'error' => $result]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveConfig(Request $request): array
    {
        $smtp = $request->validate($this->rules());

        // Password vuota nel form = usa quella salvata.
        if (($smtp['password'] ?? '') === '') {
            $smtp['password'] = Setting::get('smtp_password');
        }

        return $smtp;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'string', 'email', 'max:255'],
            'reply_to' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }
}
