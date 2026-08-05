<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
    /**
     * Rigenera la chiave API: la precedente smette subito di funzionare.
     */
    public function regenerate(): RedirectResponse
    {
        Setting::set('api_key', Str::random(48));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Chiave API rigenerata. Aggiorna le integrazioni esistenti.']);

        return back();
    }
}
