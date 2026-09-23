<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WarrantyController;
use App\Http\Controllers\WarrantyClaimController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/reports/warranties', [DashboardController::class, 'warranties']);
    Route::get('/reports/claims', [DashboardController::class, 'claims']);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('vendors', VendorController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-units', ProductUnitController::class);
    Route::apiResource('warranties', WarrantyController::class);
    Route::apiResource('claims', WarrantyClaimController::class)->only(['index', 'store', 'show', 'update']);
    Route::patch('claims/{claim}/status', [WarrantyClaimController::class, 'updateStatus']);
});
