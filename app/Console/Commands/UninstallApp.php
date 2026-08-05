<?php

namespace App\Console\Commands;

use App\Support\InstallState;
use Illuminate\Console\Command;

class UninstallApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:uninstall {--force : Salta la conferma}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancella il flag di installazione per rivedere il wizard da capo';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Cancellare il flag di installazione e riaprire /install?')) {
            return self::SUCCESS;
        }

        InstallState::forgetCompletion();

        if (InstallState::installed()) {
            unlink(InstallState::path());
        }

        $this->info('Flag di installazione cancellato: al prossimo accesso si riaprirà /install.');

        return self::SUCCESS;
    }
}
