<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\CareerController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ScholarshipController;
use App\Http\Controllers\Api\TutoringController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/careers', [CareerController::class, 'index']);

Route::post('/chat', [ChatController::class, 'send']);
Route::get('/chat/history', [ChatController::class, 'getHistory']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(\Illuminate\Http\Request $req) => $req->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/chat/auth', [ChatController::class, 'sendAuth']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);

    Route::apiResource('careers', CareerController::class)->except('index');
    Route::apiResource('grades', GradeController::class);
    Route::apiResource('schedules', ScheduleController::class);
    Route::apiResource('scholarships', ScholarshipController::class);
    Route::apiResource('tutorings', TutoringController::class);
});
