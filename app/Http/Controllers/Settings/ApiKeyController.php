<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    /**
     * Elenco delle chiavi API (solo admin).
     */
    public function index(): Response
    {
        return Inertia::render('settings/ApiKeys', [
            'apiKeys' => ApiKey::query()
                ->orderBy('id')
                ->get()
                ->map(fn (ApiKey $apiKey): array => [
                    'id' => $apiKey->id,
                    'name' => $apiKey->name,
                    'key' => $apiKey->plainKey(),
                    'created_at' => $apiKey->created_at?->format('d/m/Y H:i'),
                    'updated_at' => $apiKey->updated_at?->format('d/m/Y H:i'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        ApiKey::generate($data['name']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Chiave API creata.']);

        return back();
    }

    /**
     * Rinomina la chiave: il segreto resta invariato.
     */
    public function update(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $data = $request->validate($this->rules($apiKey));

        $apiKey->update(['name' => $data['name']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Chiave API rinominata.']);

        return back();
    }

    /**
     * Rigenera il segreto: il precedente smette subito di funzionare.
     */
    public function regenerate(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Chiave \"{$apiKey->name}\" rigenerata. Aggiorna le integrazioni che la usano."]);

        return back();
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Chiave \"{$apiKey->name}\" eliminata."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(?ApiKey $apiKey = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:64',
                Rule::unique(ApiKey::class, 'name')->ignore($apiKey?->id),
            ],
        ];
    }
}
