<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutService
{
    public function execute(Request $request): void
    {
        $user = $request->user();
        
        if ($user) {
            // Revoke all tokens for the user (for API token authentication)
            $user->tokens()->delete();
            
            // Also logout from web session if exists
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
            }
        }
    }
} 