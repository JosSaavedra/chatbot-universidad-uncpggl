<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'chatbot-uncpggl-api',
        'status' => 'ok',
        'docs' => '/up',
    ]);
});
