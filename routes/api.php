<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Versioned payment gateway webhook endpoint. Provider verification and
// payload signature checks live in ProviderRegistry and the active PaymentAdapter.
Route::post('/v1/webhooks/payments/{provider}', [WebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('api.webhooks.payments');
