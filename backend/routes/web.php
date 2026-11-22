<?php

use App\Http\Controllers\Web\QrApprovalController;
use App\Http\Controllers\Web\EmailQrApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'chatbot-uncpggl-api',
        'status' => 'ok',
        'docs' => '/up',
    ]);
});

Route::get('/acceso/qr/{token}', [QrApprovalController::class, 'show'])
    ->where('token', '[a-f0-9]{64}')
    ->middleware('throttle:60,1,qr-scan:')
    ->name('qr.scan');

Route::get('/auth/google/start', [QrApprovalController::class, 'startGoogle'])
    ->middleware('throttle:20,1,google-start:')
    ->name('qr.google.start');

Route::get('/auth/google/callback', [QrApprovalController::class, 'callback'])
    ->middleware('throttle:20,1,google-callback:')
    ->name('qr.google.callback');

Route::get('/acceso/qr/revisar', [QrApprovalController::class, 'review'])
    ->name('qr.review');

Route::post('/acceso/qr/autorizar', [QrApprovalController::class, 'approve'])
    ->middleware('throttle:20,1,qr-decision:')
    ->name('qr.approve');

Route::post('/acceso/qr/rechazar', [QrApprovalController::class, 'reject'])
    ->middleware('throttle:20,1,qr-decision:')
    ->name('qr.reject');

Route::prefix('/acceso/correo')->where(['token' => '[a-f0-9]{64}'])->group(function () {
    Route::get('/qr/{token}', [EmailQrApprovalController::class, 'scan'])
        ->middleware('throttle:60,1,email-scan:')->name('qr.email.scan');
    Route::post('/qr/{token}', [EmailQrApprovalController::class, 'send'])
        ->middleware('throttle:10,1,email-send:')->name('qr.email.send');
    Route::get('/revisar/{token}', [EmailQrApprovalController::class, 'review'])
        ->middleware('throttle:30,1,email-review:')->name('qr.email.review');
    Route::post('/revisar/{token}', [EmailQrApprovalController::class, 'decide'])
        ->middleware('throttle:qr-email-decision')->name('qr.email.decide');
});
