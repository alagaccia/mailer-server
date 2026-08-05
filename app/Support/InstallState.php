<?php

namespace App\Support;

class InstallState
{
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
}
