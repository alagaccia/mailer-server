<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Installa Composer "in locale" dentro al progetto (base_path/composer.phar),
 * per gli hosting dove non è disponibile a livello di sistema.
 */
class ComposerInstaller
{
    protected const PHAR_URL = 'https://getcomposer.org/download/latest-stable/composer.phar';

    protected const SHA_URL = self::PHAR_URL.'.sha256sum';

    protected static ?string $pathOverride = null;

    /**
     * Sovrascrive il percorso del phar (usato nei test).
     */
    public static function usePath(?string $path): void
    {
        static::$pathOverride = $path;
    }

    /**
     * Stato di Composer sulla macchina: presente nel progetto, nel sistema o assente.
     *
     * @return array{available: bool, source: 'project'|'system'|null, path: string|null, version: string|null}
     */
    public static function status(): array
    {
        if (is_file(static::path())) {
            return [
                'available' => true,
                'source' => 'project',
                'path' => static::path(),
                'version' => static::version([PHP_BINARY, static::path(), '--version']),
            ];
        }

        $version = static::version(['composer', '--version']);

        if ($version !== null) {
            return [
                'available' => true,
                'source' => 'system',
                'path' => 'composer',
                'version' => $version,
            ];
        }

        return [
            'available' => false,
            'source' => null,
            'path' => null,
            'version' => null,
        ];
    }

    /**
     * Percorso del phar dentro al progetto.
     */
    public static function path(): string
    {
        return static::$pathOverride ?? base_path('composer.phar');
    }

    /**
     * Scarica composer.phar nel progetto verificandone lo sha256.
     * Se è già presente non fa nulla.
     *
     * @return array{available: bool, source: 'project'|'system'|null, path: string|null, version: string|null}
     *
     * @throws RuntimeException
     */
    public static function install(): array
    {
        $path = static::path();

        if (! is_file($path)) {
            if (! is_writable(dirname($path))) {
                throw new RuntimeException(__('La cartella del progetto non è scrivibile: impossibile installare Composer.'));
            }

            static::download($path);
        }

        static::ignoreInGit();

        return static::status();
    }

    /**
     * Scarica e verifica il phar, scrivendolo in modo atomico.
     */
    protected static function download(string $path): void
    {
        try {
            $sha = Http::timeout(30)->get(static::SHA_URL);
            $phar = Http::timeout(120)->get(static::PHAR_URL);
        } catch (Throwable $e) {
            throw new RuntimeException(__('Download di Composer non riuscito: :error', ['error' => $e->getMessage()]));
        }

        if (! $sha->successful() || ! $phar->successful()) {
            throw new RuntimeException(__('Download di Composer non riuscito: getcomposer.org non raggiungibile.'));
        }

        // Il file .sha256sum ha il formato "<hash>  composer.phar".
        $expected = strtolower(trim(explode(' ', trim($sha->body()))[0]));
        $actual = hash('sha256', $phar->body());

        if ($expected === '' || ! hash_equals($expected, $actual)) {
            throw new RuntimeException(__('Il file scaricato non corrisponde alla firma ufficiale: installazione annullata.'));
        }

        $tmp = $path.'.tmp';

        if (file_put_contents($tmp, $phar->body()) === false) {
            throw new RuntimeException(__('Impossibile scrivere composer.phar nella cartella del progetto.'));
        }

        @chmod($tmp, 0755);

        if (! rename($tmp, $path)) {
            @unlink($tmp);

            throw new RuntimeException(__('Impossibile scrivere composer.phar nella cartella del progetto.'));
        }

        if (static::version([PHP_BINARY, $path, '--version']) === null) {
            @unlink($path);

            throw new RuntimeException(__('Composer è stato scaricato ma non è eseguibile su questo server.'));
        }
    }

    /**
     * Il phar non va versionato: aggiunge la riga al .gitignore se manca.
     */
    protected static function ignoreInGit(): void
    {
        // Ha senso solo se il phar sta davvero nella radice del progetto.
        if (dirname(static::path()) !== rtrim(base_path(), '/')) {
            return;
        }

        $gitignore = base_path('.gitignore');

        if (! is_file($gitignore) || ! is_writable($gitignore)) {
            return;
        }

        $content = (string) file_get_contents($gitignore);

        if (preg_match('#^/?composer\.phar$#m', $content)) {
            return;
        }

        file_put_contents($gitignore, rtrim($content, "\n")."\n/composer.phar\n");
    }

    /**
     * Versione riportata dal comando, oppure null se non eseguibile.
     *
     * @param  list<string>  $command
     */
    protected static function version(array $command): ?string
    {
        try {
            $process = new Process($command, base_path());
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $output = trim($process->getOutput());

            if ($output === '') {
                return null;
            }

            return strtok($output, "\n") ?: null;
        } catch (ProcessException) {
            return null;
        }
    }
}
