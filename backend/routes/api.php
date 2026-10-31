<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AudioTranscriptionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaidServiceRequestController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WarrantyClaimController;
use App\Http\Controllers\WarrantyController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/reports/warranties', [DashboardController::class, 'warranties']);
    Route::get('/reports/claims', [DashboardController::class, 'claims']);
    Route::post('/claims/summarize', [WarrantyClaimController::class, 'summarize']);
    Route::get('/paid-service-requests/eligible-units', [PaidServiceRequestController::class, 'eligibleUnits']);
    Route::get('/paid-service-requests', [PaidServiceRequestController::class, 'index']);
    Route::post('/paid-service-requests', [PaidServiceRequestController::class, 'store']);
    Route::patch('/paid-service-requests/{paidServiceRequest}', [PaidServiceRequestController::class, 'update']);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('vendors', VendorController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-units', ProductUnitController::class);
    Route::apiResource('warranties', WarrantyController::class);
    Route::apiResource('claims', WarrantyClaimController::class)->only(['index', 'store', 'show', 'update']);
    Route::patch('claims/{claim}/receipt', [WarrantyClaimController::class, 'recordReceipt']);
    Route::get('claims/{claim}/receipt', [WarrantyClaimController::class, 'downloadReceipt']);
    Route::get('claims/{claim}/vendor-dispatch', [WarrantyClaimController::class, 'downloadVendorDispatch']);
    Route::post('claims/{claim}/evidence-photo', [WarrantyClaimController::class, 'uploadEvidencePhoto']);
    Route::patch('claims/{claim}/status', [WarrantyClaimController::class, 'updateStatus']);
    Route::post('claims/transcribe', AudioTranscriptionController::class)
        ->middleware('throttle:3,1');
});
