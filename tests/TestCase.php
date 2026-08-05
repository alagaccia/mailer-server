<?php

namespace Tests;

use App\Models\Setting;
use App\Support\ComposerInstaller;
use App\Support\InstallState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected string $installFlagPath;

    protected string $composerPharPath;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushResolved();

        // Di default i test girano ad applicazione "installata": il flag
        // punta a un file temporaneo così da non toccare storage/app.
        $this->installFlagPath = tempnam(sys_get_temp_dir(), 'mailer-installed-');
        file_put_contents($this->installFlagPath, '{}');
        InstallState::usePath($this->installFlagPath);

        // Il phar di Composer non deve mai finire nella cartella del progetto
        // durante i test.
        $this->composerPharPath = sys_get_temp_dir().'/mailer-composer-'.uniqid().'.phar';
        ComposerInstaller::usePath($this->composerPharPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->installFlagPath)) {
            unlink($this->installFlagPath);
        }

        InstallState::usePath(null);

        if (is_file($this->composerPharPath)) {
            unlink($this->composerPharPath);
        }

        ComposerInstaller::usePath(null);

        parent::tearDown();
    }

    /**
     * Simula lo stato "non installato".
     */
    protected function markNotInstalled(): void
    {
        if (is_file($this->installFlagPath)) {
            unlink($this->installFlagPath);
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
