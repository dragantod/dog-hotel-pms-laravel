<?php

use App\Http\Controllers\UserSiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// User-Site relationship management
Route::middleware(['api', 'auth:sanctum'])
    ->prefix('v1')
    ->group(function () {
        // Get sites for a specific user
        Route::get('users/{user}/sites', [UserSiteController::class, 'index']);
        
        // Assign a site to a user  
        Route::post('user-sites', [UserSiteController::class, 'store']);
        
        // Remove a site from a user
        Route::delete('users/{user}/sites/{siteId}', [UserSiteController::class, 'destroy'])
            ->where('siteId', '[0-9]+');
    });
