<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->post('/install/finalize')->assertNotFound();
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
