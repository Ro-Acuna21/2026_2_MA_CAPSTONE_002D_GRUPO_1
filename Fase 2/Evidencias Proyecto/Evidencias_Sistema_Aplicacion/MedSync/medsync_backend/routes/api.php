<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProfessionalController;
use App\Http\Controllers\Api\ProfessionalAccessController;
use App\Http\Controllers\Api\AccountActivationController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\PlatformSpecialtyController;
use App\Http\Controllers\Api\PlatformCenterController;
use App\Http\Controllers\Api\PatientEnrollmentController;
use App\Http\Controllers\Api\CenterAccessController;
use App\Http\Controllers\Api\CenterPermissionCatalogController;
use App\Http\Controllers\Api\CenterSpecialtyController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\TerritorialCatalogController;
use App\Http\Controllers\Api\MedicalResultController;
use App\Http\Controllers\Api\ResultTypeController;
use App\Http\Controllers\Api\ServicePriceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\CommercialInquiryController;
Route::get('/public/centers', [AuthController::class, 'publicCenters']);
Route::get('/public/territorial-catalog', [TerritorialCatalogController::class, 'index']);
Route::get('/public/plans', [PlanController::class, 'publicIndex']);
Route::post('/public/commercial-inquiries', [CommercialInquiryController::class, 'store'])->middleware('throttle:5,1');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post(
    '/auth/activate-account',
    [AccountActivationController::class, 'store']
);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/v1/patient-enrollment', [PatientEnrollmentController::class, 'show']);
    Route::post('/v1/patient-enrollment', [PatientEnrollmentController::class, 'store']);
});

// Catálogo global de especialidades (plataforma): exclusivo de SUPER_ADMIN,
// nunca abre una conexión clínica (ver api-contract.md, 24-10-2026).
Route::middleware('auth:sanctum')->prefix('v1/platform')->group(function () {
    Route::get('/commercial-inquiries', [CommercialInquiryController::class, 'index']);
    Route::get('/plans', [PlanController::class, 'index']);
    Route::post('/plans', [PlanController::class, 'store']);
    Route::patch('/plans/{plan}', [PlanController::class, 'update'])->whereNumber('plan');
    Route::get('/centers', [PlatformCenterController::class, 'index']);
    Route::get('/centers/{center}/subscription', [SubscriptionController::class, 'platformShow'])->whereNumber('center');
    Route::post('/centers/{center}/subscriptions', [SubscriptionController::class, 'store'])->whereNumber('center');
    Route::patch('/centers/{center}/subscriptions/{subscription}', [SubscriptionController::class, 'update'])->whereNumber('center')->whereNumber('subscription');
    Route::post('/centers/{center}/subscriptions/{subscription}/payments', [SubscriptionController::class, 'payment'])->whereNumber('center')->whereNumber('subscription');
    Route::post('/centers', [PlatformCenterController::class, 'store']);
    Route::patch('/centers/{center}', [PlatformCenterController::class, 'update'])->whereNumber('center');
    Route::post('/centers/{center}/provision', [PlatformCenterController::class, 'retry'])->whereNumber('center');
    Route::get('/centers/{center}/administrators', [CenterAccessController::class, 'platformIndex'])->whereNumber('center');
    Route::post('/centers/{center}/administrators', [CenterAccessController::class, 'platformStore'])->whereNumber('center');
    Route::patch('/centers/{center}/administrators/{membership}', [CenterAccessController::class, 'platformUpdate'])->whereNumber('center')->whereNumber('membership');
    Route::post('/centers/{center}/administrators/{membership}/resend-invitation', [CenterAccessController::class, 'platformResend'])->whereNumber('center')->whereNumber('membership');
    Route::get('/centers/{center}/staff', [CenterAccessController::class, 'platformStaff'])->whereNumber('center');
    Route::patch('/centers/{center}/staff/{membership}/role', [CenterAccessController::class, 'platformChangeStaffRole'])->whereNumber('center')->whereNumber('membership');
    Route::get('/specialties', [PlatformSpecialtyController::class, 'index']);
    Route::post('/specialties', [PlatformSpecialtyController::class, 'store']);
    Route::patch('/specialties/{specialty}', [PlatformSpecialtyController::class, 'update'])->whereNumber('specialty');
});

Route::middleware(['auth:sanctum', 'tenant.center'])->group(function () {
    Route::get('/v1/center/subscription', [SubscriptionController::class, 'centerShow']);
    Route::get('/v1/center/permissions', CenterPermissionCatalogController::class);
    Route::get('/v1/center/receptionists', [CenterAccessController::class, 'centerIndex']);
    Route::post('/v1/center/receptionists', [CenterAccessController::class, 'centerStore']);
    Route::patch('/v1/center/receptionists/{membership}', [CenterAccessController::class, 'centerUpdate'])->whereNumber('membership');
    Route::post('/v1/center/receptionists/{membership}/resend-invitation', [CenterAccessController::class, 'centerResend'])->whereNumber('membership');
    Route::get('/v1/me', [AuthController::class, 'me']);
    Route::get('/v1/result-types', [ResultTypeController::class, 'index']);
    Route::post('/v1/result-types', [ResultTypeController::class, 'store']);
    Route::patch('/v1/result-types/{resultType}', [ResultTypeController::class, 'update'])->whereNumber('resultType');
    Route::get('/v1/results', [MedicalResultController::class, 'index']);
    Route::post('/v1/results', [MedicalResultController::class, 'store']);
    Route::post('/v1/results/{result}/publish', [MedicalResultController::class, 'publish'])->whereNumber('result');
    Route::post('/v1/results/{result}/void', [MedicalResultController::class, 'void'])->whereNumber('result');
    Route::get('/v1/results/{result}/files/{file}', [MedicalResultController::class, 'download'])->whereNumber('result')->whereNumber('file');
    Route::get('/v1/service-prices', [ServicePriceController::class, 'index']);
    Route::post('/v1/service-prices', [ServicePriceController::class, 'store']);
    Route::patch('/v1/service-prices/{price}', [ServicePriceController::class, 'update'])->whereNumber('price');
    Route::delete('/v1/service-prices/{price}', [ServicePriceController::class, 'destroy'])->whereNumber('price');
    Route::get('/v1/reports/summary', ReportController::class);
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
    Route::get('/v1/services/{service}/price', [ServiceController::class, 'price'])->whereNumber('service');
    Route::get('/v1/services/{service}/professionals', [ServiceController::class, 'professionals']);
    Route::get('/v1/availabilities', [AvailabilityController::class, 'index']);
    Route::post('/v1/availabilities', [AvailabilityController::class, 'store']);
    Route::patch('/v1/availabilities/{availability}', [AvailabilityController::class, 'update'])->whereNumber('availability');
    Route::get('/v1/appointments/available-slots', [AppointmentController::class, 'availableSlots']);
    Route::get('/v1/appointments', [AppointmentController::class, 'index']);
    Route::get('/v1/appointments/my', [AppointmentController::class, 'myAppointments']);
    Route::get('/v1/appointments/{appointment}/history', [AppointmentController::class, 'history'])->whereNumber('appointment');
    Route::post('/v1/appointments', [AppointmentController::class, 'store']);
    Route::patch('/v1/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
    Route::patch('/v1/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
    Route::patch('/v1/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);

    // Administración del centro: especialidades (API local heredada) y prestaciones.
    Route::get('/v1/specialties', [SpecialtyController::class, 'index']);
    Route::post('/v1/services', [ServiceController::class, 'store']);
    Route::patch('/v1/services/{service}', [ServiceController::class, 'update'])->whereNumber('service');

    // Selección del centro en el catálogo global: el centro solo activa o
    // desactiva su propia fila (ver CenterSpecialtyController).
    Route::get('/v1/center/specialties', [CenterSpecialtyController::class, 'index']);
    Route::put('/v1/center/specialties/{specialty}', [CenterSpecialtyController::class, 'update'])->whereNumber('specialty');
});
