<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\UniversityEventController;
use App\Http\Controllers\Api\SedeController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\CareerController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ScholarshipController;
use App\Http\Controllers\Api\TutoringController;
use App\Http\Controllers\Api\PaymentController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/login/carnet', [AuthController::class, 'loginCarnet'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

Route::get('/careers', [CareerController::class, 'index']);

Route::get('/sedes', [SedeController::class, 'index'])->middleware('throttle:120,1');
Route::get('/sedes/{id}', [SedeController::class, 'show']);

Route::get('/calendar', [CalendarController::class, 'index'])->middleware('throttle:120,1');
Route::get('/calendar/holidays', [CalendarController::class, 'holidays'])->middleware('throttle:120,1');
Route::get('/university-events', [UniversityEventController::class, 'index'])->middleware('throttle:120,1');
Route::get('/university-events/categories', [UniversityEventController::class, 'categories']);
Route::get('/university-events/{id}', [UniversityEventController::class, 'show']);

Route::post('/chat', [ChatController::class, 'send'])->middleware('throttle:60,1');
Route::get('/chat/history', [ChatController::class, 'getHistory'])->middleware('throttle:60,1');

// Consulta de pagos/recibos: requiere sesión y sólo devuelve los pagos del
// estudiante autenticado (se movió al grupo protegido más abajo).

// Horarios generales (públicos): catálogo de carreras/años/grupos y consulta por
// carrera, año y grupo. No exponen datos personales de ningún estudiante.
Route::get('/schedules/options', [ScheduleController::class, 'options'])->middleware('throttle:120,1');
Route::get('/schedules/general', [ScheduleController::class, 'general'])->middleware('throttle:120,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(\Illuminate\Http\Request $req) => $req->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/chat/auth', [ChatController::class, 'sendAuth'])->middleware('throttle:60,1');

    // Horario personalizado del estudiante autenticado (sólo su propia información).
    Route::get('/my-schedule', [ScheduleController::class, 'mySchedule'])->middleware('throttle:120,1');

    // Pagos/recibos: sólo los del estudiante autenticado (no se puede consultar
    // el de otros carnés).
    Route::get('/student-payments', [PaymentController::class, 'lookup'])->middleware('throttle:30,1');
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);

    // Calificaciones del ESTUDIANTE AUTENTICADO, agrupadas por período. Se
    // obtiene el usuario solo del token: no acepta user_id ni carné de otro.
    Route::get('/my-grades', [GradeController::class, 'myGrades'])->middleware('throttle:30,1');

    Route::apiResource('careers', CareerController::class)->except('index');
    Route::apiResource('grades', GradeController::class);
    Route::apiResource('schedules', ScheduleController::class);
    Route::apiResource('scholarships', ScholarshipController::class);
    Route::apiResource('tutorings', TutoringController::class);

    // Administración de eventos universitarios (solo administradores)
    Route::middleware('admin')->group(function () {
        Route::post('/university-events', [UniversityEventController::class, 'store']);
        Route::put('/university-events/{id}', [UniversityEventController::class, 'update']);
        Route::patch('/university-events/{id}', [UniversityEventController::class, 'update']);
        Route::delete('/university-events/{id}', [UniversityEventController::class, 'destroy']);

        // Administración de sedes (solo administradores)
        Route::post('/sedes', [SedeController::class, 'store']);
        Route::put('/sedes/{id}', [SedeController::class, 'update']);
        Route::patch('/sedes/{id}', [SedeController::class, 'update']);
        Route::delete('/sedes/{id}', [SedeController::class, 'destroy']);
    });
});
