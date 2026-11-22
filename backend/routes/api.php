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
use App\Http\Controllers\Api\QrAuthController;

Route::get('/auth/mode', fn () => [
    'mode' => config('student_access.demo') ? 'demo' : 'institutional',
]);
// Acceso local: carné (o correo) + contraseña individual. El limitador
// `login` restringe los intentos por cuenta y por IP.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::prefix('qr-auth')->group(function () {
    Route::post('/request', [QrAuthController::class, 'request'])->middleware('throttle:10,1,qr-create:');
    Route::get('/status/{publicId}', [QrAuthController::class, 'status'])
        ->where('publicId', '[a-f0-9]{32}')->middleware('throttle:qr-poll');
    Route::post('/exchange', [QrAuthController::class, 'exchange'])->middleware('throttle:30,1,qr-exchange:');
    Route::post('/cancel', [QrAuthController::class, 'cancel'])->middleware('throttle:30,1,qr-cancel:');
});
// El registro público está CERRADO: las cuentas de estudiante se aprovisionan
// por la administración (ver `php artisan student:set-password`) o por la
// ceremonia QR + Google. No existe auto-registro con acceso a la API.

Route::get('/careers', [CareerController::class, 'index']);

Route::get('/sedes', [SedeController::class, 'index'])->middleware('throttle:120,1');
Route::get('/sedes/{id}', [SedeController::class, 'show']);

Route::get('/calendar', [CalendarController::class, 'index'])->middleware('throttle:120,1');
Route::get('/calendar/holidays', [CalendarController::class, 'holidays'])->middleware('throttle:120,1');
Route::get('/university-events', [UniversityEventController::class, 'index'])->middleware('throttle:120,1');
Route::get('/university-events/categories', [UniversityEventController::class, 'categories']);
Route::get('/university-events/{id}', [UniversityEventController::class, 'show']);

Route::post('/chat', [ChatController::class, 'send'])->middleware(['throttle:chat', 'chat.capacity']);
Route::get('/chat/history', [ChatController::class, 'getHistory'])->middleware('throttle:60,1');
Route::delete('/chat/history', [ChatController::class, 'destroy'])->middleware('throttle:20,1,chat-delete:');

// Consulta de pagos/recibos: requiere sesión y sólo devuelve los pagos del
// estudiante autenticado (se movió al grupo protegido más abajo).

// Horarios generales (públicos): catálogo de carreras/años/grupos y consulta por
// carrera, año y grupo. No exponen datos personales de ningún estudiante.
Route::get('/schedules/options', [ScheduleController::class, 'options'])->middleware('throttle:120,1');
Route::get('/schedules/general', [ScheduleController::class, 'general'])->middleware('throttle:120,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(\Illuminate\Http\Request $req) => $req->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/chat/auth', [ChatController::class, 'sendAuth'])->middleware(['throttle:chat', 'chat.capacity']);

    // Horario personalizado del estudiante autenticado (sólo su propia información).
    Route::get('/my-schedule', [ScheduleController::class, 'mySchedule'])->middleware('throttle:120,1');

    // Pagos/recibos: sólo los del estudiante autenticado (no se puede consultar
    // el de otros carnés).
    Route::get('/student-payments', [PaymentController::class, 'lookup'])->middleware('throttle:30,1');
    Route::get('/student-payments/{id}/receipt', [PaymentController::class, 'receipt'])
        ->whereNumber('id')->middleware('throttle:30,1');
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);

    // Calificaciones del ESTUDIANTE AUTENTICADO, agrupadas por período. Se
    // obtiene el usuario solo del token: no acepta user_id ni carné de otro.
    Route::get('/my-grades', [GradeController::class, 'myGrades'])->middleware('throttle:30,1');

    Route::apiResource('careers', CareerController::class)->only('show');
    Route::apiResource('grades', GradeController::class);
    Route::apiResource('schedules', ScheduleController::class)->only(['index', 'show']);
    Route::apiResource('scholarships', ScholarshipController::class)->only(['index', 'show']);
    Route::apiResource('tutorings', TutoringController::class)->only(['index', 'show']);

    // Administración de eventos universitarios (solo administradores)
    // Doble capa de autorización para carreras, horarios, becas y tutorías:
    //   1) el middleware `admin` restringe las rutas de escritura, y
    //   2) las Policies (Career/Schedule/Scholarship/Tutoring) vuelven a
    //      comprobar el permiso dentro de cada controlador.
    Route::middleware('admin')->group(function () {
        Route::apiResource('careers', CareerController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('schedules', ScheduleController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('scholarships', ScholarshipController::class)->except(['index', 'show']);
        Route::apiResource('tutorings', TutoringController::class)->except(['index', 'show']);
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
