<?php

use App\Http\Controllers\Api\SendController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\VerifyApiKey;
use Illuminate\Support\Facades\Route;

Route::post('/send', SendController::class)
    ->middleware([EnsureInstalled::class, VerifyApiKey::class])
    ->name('api.send');

/*
 * Impostazioni del webhook di default (Impostazioni → Webhook) via API:
 * le scrive `php artisan mailer-transport:install` di alagaccia/mailer-transport.
 */
Route::middleware([EnsureInstalled::class, VerifyApiKey::class])
    ->name('api.webhook.')
    ->group(function () {
        Route::get('/webhook', [WebhookController::class, 'show'])->name('show');
        Route::put('/webhook', [WebhookController::class, 'update'])->name('update');
    });
