<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    public function test_uninstalled_app_redirects_web_requests_to_installer(): void
    {
        $this->markNotInstalled();

        $this->get('/login')->assertRedirect('/install');
        $this->get('/dashboard')->assertRedirect('/install');
    }

    public function test_uninstalled_app_shows_installer(): void
    {
        $this->markNotInstalled();

        $this->get('/install')->assertOk();
    }

    public function test_installed_app_hides_installer_routes(): void
    {
        $this->get('/install')->assertNotFound();
        $this->post('/install/validate-admin')->assertNotFound();
        $this->post('/install/test-database')->assertNotFound();
        $this->post('/install/test-smtp')->assertNotFound();
        $this->post('/install/composer')->assertNotFound();
        $this->post('/install/finalize')->assertNotFound();
    }

    public function test_composer_step_downloads_the_phar_into_the_project(): void
    {
        $this->markNotInstalled();

        // Un phar "finto" ma eseguibile: l'installer verifica che risponda a
        // --version prima di tenerlo.
        $phar = '<?php echo "Composer version 2.9.9 2026-01-01"."\n";';

        Http::fake([
            'getcomposer.org/*.sha256sum' => Http::response(hash('sha256', $phar).'  composer.phar'),
            'getcomposer.org/*' => Http::response($phar),
        ]);

        $this->postJson('/install/composer')
            ->assertOk()
            ->assertJsonPath('composer.available', true)
            ->assertJsonPath('composer.source', 'project')
            ->assertJsonPath('composer.path', $this->composerPharPath);

        $this->assertFileExists($this->composerPharPath);
    }

    public function test_composer_step_rejects_a_phar_with_a_wrong_checksum(): void
    {
        $this->markNotInstalled();

        Http::fake([
            'getcomposer.org/*.sha256sum' => Http::response(str_repeat('a', 64).'  composer.phar'),
            'getcomposer.org/*' => Http::response('<?php // manomesso'),
        ]);

        $this->postJson('/install/composer')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['composer']);

        $this->assertFileDoesNotExist($this->composerPharPath);
    }

    public function test_validate_admin_step_validates_input(): void
    {
        $this->markNotInstalled();

        $this->postJson('/install/validate-admin', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'secret123',
            'password_confirmation' => 'different',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->postJson('/install/validate-admin', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertOk()
            ->assertExactJson(['ok' => true]);
    }
}
