<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication routes (public)
Route::prefix('v1/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('v1')
    ->group(function () {
        Route::get('user/me', [UserController::class, 'me']);

        Route::get('users/{user}/sites', [UserSiteController::class, 'index']);
        Route::post('user-sites', [UserSiteController::class, 'store']);
        Route::delete('users/{user}/sites/{siteId}', [UserSiteController::class, 'destroy'])
            ->where('siteId', '[0-9]+');
    });
