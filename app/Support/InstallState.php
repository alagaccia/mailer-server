<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class InstallState
{
    /**
     * Per quanto tempo la schermata finale dell'installer resta raggiungibile.
     */
    public const COMPLETION_TTL_MINUTES = 120;

    protected static ?string $pathOverride = null;

    /**
     * Sovrascrive il percorso del flag (usato nei test).
     */
    public static function usePath(?string $path): void
    {
        static::$pathOverride = $path;
    }

    public static function path(): string
    {
        return static::$pathOverride ?? storage_path('app/installed.json');
    }

    /**
     * File temporaneo con la chiave API appena generata: tiene in vita la
     * schermata finale anche dopo un refresh, quando le rotte del wizard
     * rispondono già 404.
     */
    public static function completionPath(): string
    {
        return dirname(static::path()).'/install-completed.json';
    }

    public static function installed(): bool
    {
        return is_file(static::path());
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function markInstalled(array $meta = []): void
    {
        file_put_contents(static::path(), json_encode([
            'installed_at' => now()->toIso8601String(),
            ...$meta,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Salva la chiave API per la schermata finale e restituisce il token
     * monouso che ne autorizza la lettura.
     */
    public static function rememberCompletion(string $apiKey): string
    {
        $token = Str::random(48);

        file_put_contents(static::completionPath(), json_encode([
            'token' => $token,
            'api_key' => $apiKey,
            'expires_at' => now()->addMinutes(static::COMPLETION_TTL_MINUTES)->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $token;
    }

    /**
     * Dati della schermata finale, se il token è valido e non scaduto.
     *
     * @return array{api_key: string}|null
     */
    public static function completion(?string $token): ?array
    {
        if ($token === null || $token === '' || ! is_file(static::completionPath())) {
            return null;
        }

        $data = json_decode((string) file_get_contents(static::completionPath()), true);

        if (! is_array($data) || ! isset($data['token'], $data['api_key'], $data['expires_at'])) {
            return null;
        }

        if (now()->greaterThan(Carbon::parse($data['expires_at']))) {
            static::forgetCompletion();

            return null;
        }

        if (! hash_equals((string) $data['token'], $token)) {
            return null;
        }

        return ['api_key' => (string) $data['api_key']];
    }

    public static function forgetCompletion(): void
    {
        if (is_file(static::completionPath())) {
            unlink(static::completionPath());
        }
    }
}
