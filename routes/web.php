<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\MailerToggleController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\SmtpController;
use App\Http\Controllers\Settings\WebhookController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\RedirectIfInstalled;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(RedirectIfInstalled::class)->prefix('install')->group(function () {
    Route::get('/', [InstallerController::class, 'show'])->name('install.show');
    Route::post('validate-admin', [InstallerController::class, 'validateAdmin'])->name('install.validate-admin');
    Route::post('test-database', [InstallerController::class, 'testDatabase'])->name('install.test-database');
    Route::post('test-smtp', [InstallerController::class, 'testSmtp'])->name('install.test-smtp');
    Route::post('finalize', [InstallerController::class, 'finalize'])->name('install.finalize');
});

// Fuori dal gruppo: la schermata finale deve restare raggiungibile proprio
// quando l'installazione è già completata. L'accesso è protetto dal token
// monouso generato dal finalize.
Route::get('install/complete', [InstallerController::class, 'complete'])->name('install.complete');
Route::post('install/complete/dismiss', [InstallerController::class, 'dismissComplete'])->name('install.complete.dismiss');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('emails/{id}', [EmailController::class, 'show'])->whereNumber('id')->name('emails.show');
    Route::post('emails/{id}/send', [EmailController::class, 'send'])->whereNumber('id')->name('emails.send');

    Route::post('mailer/toggle', MailerToggleController::class)->name('mailer.toggle');

    Route::middleware('admin')->group(function () {
        Route::get('settings/smtp', [SmtpController::class, 'edit'])->name('smtp.edit');
        Route::put('settings/smtp', [SmtpController::class, 'update'])->name('smtp.update');
        Route::post('settings/smtp/test', [SmtpController::class, 'test'])->name('smtp.test');
        Route::post('settings/smtp/test-email', [SmtpController::class, 'sendTest'])->name('smtp.send-test');
        Route::get('settings/webhook', [WebhookController::class, 'edit'])->name('webhook.edit');
        Route::put('settings/webhook', [WebhookController::class, 'update'])->name('webhook.update');
        Route::post('settings/webhook/test', [WebhookController::class, 'test'])->name('webhook.test');
        Route::get('settings/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::get('settings/api-keys/docs', [ApiKeyController::class, 'docs'])->name('api-keys.docs');
        Route::post('settings/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::put('settings/api-keys/{apiKey}', [ApiKeyController::class, 'update'])->name('api-keys.update');
        Route::post('settings/api-keys/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate'])->name('api-keys.regenerate');
        Route::delete('settings/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/settings.php';
