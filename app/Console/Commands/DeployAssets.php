<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class DeployAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:assets
        {--build : Esegue "npm run build" prima di sincronizzare}
        {--dry-run : Mostra cosa verrebbe trasferito senza scrivere nulla sul server}
        {--force : Salta la conferma}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizza la cartella degli asset compilati sul server via rsync';

    public function handle(): int
    {
        $config = config('deploy.assets');

        $required = [
            'user' => 'DEPLOY_SSH_USER',
            'host' => 'DEPLOY_SSH_HOST',
            'remote_path' => 'DEPLOY_REMOTE_PATH',
        ];

        foreach ($required as $key => $variable) {
            if (blank($config[$key] ?? null)) {
                $this->error("Configurazione mancante: imposta {$variable} nel file .env");

                return self::FAILURE;
            }
        }

        $localPath = $config['local_path'];

        if (! str_starts_with($localPath, '/')) {
            $localPath = base_path($localPath);
        }

        if ($this->option('build') && ! $this->runBuild()) {
            return self::FAILURE;
        }

        if (! is_dir($localPath)) {
            $this->error("Cartella locale inesistente: {$localPath}");
            $this->line('Esegui prima "npm run build" (oppure lancia il comando con --build).');

            return self::FAILURE;
        }

        $command = $this->rsyncCommand($config, $localPath);

        $this->line($command);

        if (! $this->option('force') && ! $this->option('dry-run') && ! $this->confirm('Procedere con il deploy degli asset?', true)) {
            return self::SUCCESS;
        }

        $result = Process::forever()
            ->path(base_path())
            ->run($command, fn (string $type, string $output) => $this->output->write($output));

        if (! $result->successful()) {
            $this->error('rsync ha restituito un errore (exit code '.$result->exitCode().').');

            return self::FAILURE;
        }

        $this->info($this->option('dry-run')
            ? 'Simulazione completata: nessun file è stato modificato sul server.'
            : 'Asset sincronizzati con successo.');

        return self::SUCCESS;
    }

    /**
     * Compila gli asset in locale.
     */
    private function runBuild(): bool
    {
        $this->line('npm run build');

        $result = Process::forever()
            ->path(base_path())
            ->run('npm run build', fn (string $type, string $output) => $this->output->write($output));

        if (! $result->successful()) {
            $this->error('La build degli asset è fallita: deploy annullato.');

            return false;
        }

        return true;
    }

    /**
     * Costruisce il comando rsync a partire dalla configurazione.
     *
     * @param  array<string, mixed>  $config
     */
    private function rsyncCommand(array $config, string $localPath): string
    {
        $source = rtrim($localPath, '/').'/';
        $destination = $config['user'].'@'.$config['host'].':'.rtrim($config['remote_path'], '/');

        $parts = ['rsync', $config['rsync_options']];

        if ($this->option('dry-run')) {
            $parts[] = '--dry-run';
        }

        $port = (int) $config['port'];

        if ($port !== 0 && $port !== 22) {
            $parts[] = '-e '.escapeshellarg('ssh -p '.$port);
        }

        $parts[] = escapeshellarg($source);
        $parts[] = escapeshellarg($destination);

        return implode(' ', $parts);
    }
}
