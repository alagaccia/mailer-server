<?php

namespace App\Support;

use RuntimeException;

class EnvWriter
{
    /**
     * Scrive (o sostituisce) le chiavi indicate nel file .env in modo atomico.
     *
     * @param  array<string, string|int|null>  $values
     */
    public static function write(array $values, ?string $path = null): void
    {
        $path ??= app()->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            throw new RuntimeException("Il file {$path} non esiste o non è scrivibile.");
        }

        $content = (string) file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.static::formatValue($value);

            // Sostituisce anche le righe commentate (es. "# DB_HOST=..."),
            // così i placeholder dello scaffold non restano come doppioni.
            $pattern = '/^[ \t]*#?[ \t]*'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = (string) preg_replace($pattern, $line, $content, 1);
            } else {
                $content = rtrim($content, "\n")."\n".$line."\n";
            }
        }

        $tmp = $path.'.tmp';

        if (file_put_contents($tmp, $content) === false) {
            throw new RuntimeException("Impossibile scrivere il file temporaneo {$tmp}.");
        }

        rename($tmp, $path);
    }

    protected static function formatValue(string|int|null $value): string
    {
        $value = (string) ($value ?? '');

        if ($value === '') {
            return '';
        }

        // Valori con soli caratteri "sicuri" non richiedono virgolette
        // (inclusi `+`, `/` e `=` delle chiavi base64, come key:generate).
        if (preg_match('/^[A-Za-z0-9_.\/:@+=-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
