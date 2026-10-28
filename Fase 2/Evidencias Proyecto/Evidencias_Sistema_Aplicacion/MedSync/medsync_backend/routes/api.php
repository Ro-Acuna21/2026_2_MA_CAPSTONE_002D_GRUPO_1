<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProfessionalController;
use App\Http\Controllers\Api\ProfessionalAccessController;
use App\Http\Controllers\Api\AccountActivationController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\PlatformSpecialtyController;
use App\Http\Controllers\Api\CenterSpecialtyController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\AvailabilityController;
Route::get('/public/centers', [AuthController::class, 'publicCenters']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post(
    '/auth/activate-account',
    [AccountActivationController::class, 'store']
);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Catálogo global de especialidades (plataforma): exclusivo de SUPER_ADMIN,
// nunca abre una conexión clínica (ver api-contract.md, 24-10-2026).
Route::middleware('auth:sanctum')->prefix('v1/platform')->group(function () {
    Route::get('/specialties', [PlatformSpecialtyController::class, 'index']);
    Route::post('/specialties', [PlatformSpecialtyController::class, 'store']);
    Route::patch('/specialties/{specialty}', [PlatformSpecialtyController::class, 'update'])->whereNumber('specialty');
});

Route::middleware(['auth:sanctum', 'tenant.center'])->group(function () {
    Route::get('/v1/me', [AuthController::class, 'me']);
    Route::get('/v1/professionals',[ProfessionalController::class,'index']);
    Route::get('/v1/patients', [PatientController::class, 'index']);
    Route::get('/v1/patients/{patient}', [PatientController::class, 'show'])->whereNumber('patient');
    Route::post('/v1/patients', [PatientController::class, 'store']);
    Route::patch('/v1/patients/{patient}', [PatientController::class, 'update'])->whereNumber('patient');
    Route::post('/v1/professionals',[ProfessionalController::class,'store']);
    Route::patch('/v1/professionals/{professional}', [ProfessionalController::class, 'update'])->whereNumber('professional');
    Route::post('/v1/professionals/{professional}/enable-access',[ProfessionalAccessController::class, 'enable']);
    Route::post('/v1/professionals/{professional}/resend-invitation',[ProfessionalAccessController::class, 'resendInvitation']);

    // Reservas y disponibilidad (README_RESERVAS_DISPONIBILIDAD_BD.md).
    Route::get('/v1/services', [ServiceController::class, 'index']);
    Route::get('/v1/services/{service}/professionals', [ServiceController::class, 'professionals']);
    Route::get('/v1/availabilities', [AvailabilityController::class, 'index']);
    Route::post('/v1/availabilities', [AvailabilityController::class, 'store']);
    Route::patch('/v1/availabilities/{availability}', [AvailabilityController::class, 'update'])->whereNumber('availability');
    Route::get('/v1/appointments/available-slots', [AppointmentController::class, 'availableSlots']);
    Route::get('/v1/appointments', [AppointmentController::class, 'index']);
    Route::get('/v1/appointments/my', [AppointmentController::class, 'myAppointments']);
    Route::post('/v1/appointments', [AppointmentController::class, 'store']);
    Route::patch('/v1/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
    Route::patch('/v1/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
    Route::patch('/v1/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);

    // Administración del centro: especialidades (API local heredada) y prestaciones.
    Route::get('/v1/specialties', [SpecialtyController::class, 'index']);
    Route::post('/v1/specialties', [SpecialtyController::class, 'store']);
    Route::patch('/v1/specialties/{specialty}', [SpecialtyController::class, 'update'])->whereNumber('specialty');
    Route::post('/v1/services', [ServiceController::class, 'store']);
    Route::patch('/v1/services/{service}', [ServiceController::class, 'update'])->whereNumber('service');

    // Selección del centro en el catálogo global: el centro solo activa o
    // desactiva su propia fila (ver CenterSpecialtyController).
    Route::get('/v1/center/specialties', [CenterSpecialtyController::class, 'index']);
    Route::put('/v1/center/specialties/{specialty}', [CenterSpecialtyController::class, 'update'])->whereNumber('specialty');
});
