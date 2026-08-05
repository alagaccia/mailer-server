<?php

namespace Tests\Unit;

use App\Support\EnvWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EnvWriterTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'env-writer-');
        file_put_contents($this->path, "APP_NAME=Test\nDB_CONNECTION=sqlite\n# DB_HOST=127.0.0.1\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_existing_keys_are_replaced(): void
    {
        EnvWriter::write(['DB_CONNECTION' => 'mysql'], $this->path);

        $content = file_get_contents($this->path);

        $this->assertStringContainsString("DB_CONNECTION=mysql\n", $content);
        $this->assertSame(1, substr_count($content, 'DB_CONNECTION='));
    }

    public function test_commented_placeholder_keys_are_uncommented_and_replaced(): void
    {
        EnvWriter::write(['DB_HOST' => 'localhost'], $this->path);

        $content = file_get_contents($this->path);

        $this->assertStringNotContainsString('# DB_HOST=', $content);
        $this->assertStringContainsString("DB_HOST=localhost\n", $content);
        $this->assertSame(1, substr_count($content, 'DB_HOST='));
    }

    public function test_missing_keys_are_appended(): void
    {
        EnvWriter::write(['DB_PORT' => '3306'], $this->path);

        $this->assertStringContainsString("DB_PORT=3306\n", file_get_contents($this->path));
    }

    public function test_values_with_special_characters_are_quoted(): void
    {
        EnvWriter::write(['DB_PASSWORD' => 'p4!ss "word" \\x'], $this->path);

        $content = file_get_contents($this->path);

        $this->assertStringContainsString('DB_PASSWORD="p4!ss \\"word\\" \\\\x"', $content);
    }

    public function test_empty_values_are_written_as_empty(): void
    {
        EnvWriter::write(['DB_PASSWORD' => null], $this->path);

        $this->assertStringContainsString("DB_PASSWORD=\n", file_get_contents($this->path));
    }

    public function test_unwritable_file_throws(): void
    {
        $this->expectException(RuntimeException::class);

        EnvWriter::write(['A' => 'b'], $this->path.'-missing');
    }
}
