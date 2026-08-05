<?php

use App\Http\Controllers\Api\SendController;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\VerifyApiKey;
use Illuminate\Support\Facades\Route;

Route::post('/send', SendController::class)
    ->middleware([EnsureInstalled::class, VerifyApiKey::class])
    ->name('api.send');
