<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProfessionalController;
use App\Http\Controllers\Api\ProfessionalAccessController;
use App\Http\Controllers\Api\AccountActivationController;
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post(
    '/auth/activate-account',
    [AccountActivationController::class, 'store']
);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/v1/me', [AuthController::class, 'me']);
    Route::get('/v1/professionals',[ProfessionalController::class,'index']);
    Route::post('/v1/professionals',[ProfessionalController::class,'store']);
    Route::post('/v1/professionals/{professional}/enable-access',[ProfessionalAccessController::class, 'enable']);
    Route::post('/v1/professionals/{professional}/resend-invitation',[ProfessionalAccessController::class, 'resendInvitation']);
});
