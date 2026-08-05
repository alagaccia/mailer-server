<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Elenco utenti (solo admin).
     */
    public function index(): Response
    {
        return Inertia::render('users/Index', [
            'users' => User::query()
                ->orderBy('id')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'created_at' => $user->created_at?->format('d/m/Y'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'confirmed', Password::default()],
            'is_admin' => ['boolean'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'email_verified_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utente creato.']);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'password' => ['nullable', 'string', 'confirmed', Password::default()],
            'is_admin' => ['boolean'],
        ]);

        $isAdmin = (bool) ($data['is_admin'] ?? false);

        if ($user->is_admin && ! $isAdmin && $this->isLastAdmin($user)) {
            throw ValidationException::withMessages([
                'is_admin' => 'Deve esistere almeno un amministratore.',
            ]);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'is_admin' => $isAdmin,
        ]);

        if (($data['password'] ?? '') !== '') {
            $user->password = $data['password'];
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utente aggiornato.']);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'Non puoi eliminare il tuo account.',
            ]);
        }

        if ($user->is_admin && $this->isLastAdmin($user)) {
            throw ValidationException::withMessages([
                'user' => 'Deve esistere almeno un amministratore.',
            ]);
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utente eliminato.']);

        return back();
    }

    protected function isLastAdmin(User $user): bool
    {
        return ! User::query()
            ->where('is_admin', true)
            ->whereKeyNot($user->id)
            ->exists();
    }
}
