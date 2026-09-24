<?php

namespace Tests\Unit;

use App\Support\EnvBootstrap;
use PHPUnit\Framework\TestCase;

class EnvBootstrapTest extends TestCase
{
    protected string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/env-bootstrap-'.bin2hex(random_bytes(6));
        mkdir($this->base.'/storage/app', 0777, true);
        file_put_contents($this->base.'/.env.example', "APP_NAME=Test\nAPP_KEY=\nAPP_DEBUG=true\n");
    }

    protected function tearDown(): void
    {
        foreach ([
            '/.env', '/.env.example', '/storage/app/installed.json',
        ] as $file) {
            @unlink($this->base.$file);
        }
        @rmdir($this->base.'/storage/app');
        @rmdir($this->base.'/storage');
        @rmdir($this->base);

        parent::tearDown();
    }

    public function test_missing_env_is_created_from_example_with_a_key(): void
    {
        EnvBootstrap::ensure($this->base);

        $this->assertFileExists($this->base.'/.env');

        $content = file_get_contents($this->base.'/.env');

        $this->assertStringContainsString("APP_NAME=Test\n", $content);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m', $content);
        $this->assertSame(1, substr_count($content, 'APP_KEY='));
    }

    public function test_empty_key_is_filled_in_existing_env(): void
    {
        file_put_contents($this->base.'/.env', "APP_NAME=Mio\nAPP_KEY=\nDB_CONNECTION=mysql\n");

        EnvBootstrap::ensure($this->base);

        $content = file_get_contents($this->base.'/.env');

        $this->assertStringContainsString("APP_NAME=Mio\n", $content);
        $this->assertStringContainsString("DB_CONNECTION=mysql\n", $content);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:/m', $content);
    }

    public function test_existing_key_is_left_untouched(): void
    {
        file_put_contents($this->base.'/.env', "APP_KEY=base64:esistente\n");

        EnvBootstrap::ensure($this->base);

        $this->assertSame("APP_KEY=base64:esistente\n", file_get_contents($this->base.'/.env'));
    }

    public function test_nothing_happens_once_installed(): void
    {
        file_put_contents($this->base.'/storage/app/installed.json', '{}');

        EnvBootstrap::ensure($this->base);

        $this->assertFileDoesNotExist($this->base.'/.env');
    }

    public function test_missing_example_is_not_an_error(): void
    {
        unlink($this->base.'/.env.example');

        EnvBootstrap::ensure($this->base);

        $this->assertFileDoesNotExist($this->base.'/.env');
    }
}
