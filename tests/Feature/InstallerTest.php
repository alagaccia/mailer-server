<?php

namespace Tests\Feature;

use App\Support\InstallState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_completion_screen_survives_a_refresh_and_can_be_dismissed(): void
    {
        $token = InstallState::rememberCompletion('chiave-di-prova');

        // L'installer è già chiuso, ma la schermata finale resta raggiungibile.
        $this->get('/install')->assertNotFound();

        $this->get('/install/complete?token='.$token)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('installer/Complete')
                ->where('apiKey', 'chiave-di-prova'));

        // Un refresh continua a mostrare la stessa chiave.
        $this->get('/install/complete?token='.$token)->assertOk();

        $this->postJson('/install/complete/dismiss', ['token' => $token])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->get('/install/complete?token='.$token)->assertRedirect('/login');
    }

    public function test_completion_screen_requires_a_valid_token(): void
    {
        InstallState::rememberCompletion('chiave-di-prova');

        $this->get('/install/complete')->assertRedirect('/login');
        $this->get('/install/complete?token=sbagliato')->assertRedirect('/login');
    }

    public function test_completion_screen_expires(): void
    {
        $token = InstallState::rememberCompletion('chiave-di-prova');

        $this->travel(InstallState::COMPLETION_TTL_MINUTES + 1)->minutes();

        $this->get('/install/complete?token='.$token)->assertRedirect('/login');
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
