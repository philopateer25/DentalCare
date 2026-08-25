<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// WhatsApp Webhook Route (Open route for UltraMsg to send inbound messages)
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
