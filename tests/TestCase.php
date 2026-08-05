<?php

namespace Tests;

use App\Models\Setting;
use App\Support\InstallState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected string $installFlagPath;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushResolved();

        // Di default i test girano ad applicazione "installata": il flag
        // punta a un file temporaneo così da non toccare storage/app.
        $this->installFlagPath = tempnam(sys_get_temp_dir(), 'mailer-installed-');
        file_put_contents($this->installFlagPath, '{}');
        InstallState::usePath($this->installFlagPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->installFlagPath)) {
            unlink($this->installFlagPath);
        }

        InstallState::usePath(null);

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
