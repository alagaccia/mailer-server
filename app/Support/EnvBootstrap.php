<?php

namespace App\Support;

use Throwable;

/**
 * Prepara il file .env prima del primo avvio, così l'installer web non
 * richiede alcun comando da terminale: se il file manca viene copiato da
 * .env.example, se la APP_KEY è vuota ne viene generata una.
 *
 * Viene invocato da bootstrap/app.php prima che Laravel legga l'ambiente,
 * quindi lavora solo con funzioni PHP native. Non deve mai far fallire il
 * boot: in caso di errore (es. permessi) lascia tutto com'è e sarà il wizard
 * a segnalare il problema.
 */
class EnvBootstrap
{
    public static function ensure(string $basePath): void
    {
        // Dopo l'installazione il .env esiste per forza: niente da fare.
        if (is_file($basePath.'/storage/app/installed.json')) {
            return;
        }

        $env = $basePath.'/.env';
        $example = $basePath.'/.env.example';

        try {
            if (! is_file($env)) {
                if (! is_file($example) || ! is_writable($basePath)) {
                    return;
                }

                copy($example, $env);
            }

            if (static::hasAppKey($env)) {
                return;
            }

            EnvWriter::write(['APP_KEY' => static::generateKey()], $env);
        } catch (Throwable) {
            // Il wizard mostrerà l'avviso "file .env non scrivibile".
        }
    }

    public static function hasAppKey(string $envPath): bool
    {
        $content = (string) file_get_contents($envPath);

        return preg_match('/^[ \t]*APP_KEY=[ \t]*\S/m', $content) === 1;
    }

    /**
     * Stesso formato di `php artisan key:generate` (AES-256-CBC).
     */
    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }
}
