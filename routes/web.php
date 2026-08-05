<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\MailerToggleController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\SmtpController;
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
        Route::post('settings/api-key/regenerate', [ApiKeyController::class, 'regenerate'])->name('api-key.regenerate');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/settings.php';
