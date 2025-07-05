<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Exceptions\UnauthorizedException;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $guard = null)
    {
        $authGuard = app('auth')->guard($guard);

        if ($authGuard->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $user = $authGuard->user();

        // Get the user's company ID for team-based permissions
        $companyId = $user->company_id;

        if (!$companyId) {
            throw UnauthorizedException::forPermissions([$permission]);
        }

        // Check if user has permission for their company (team)
        if (!$user->hasPermissionTo($permission, $companyId)) {
            throw UnauthorizedException::forPermissions([$permission]);
        }

        return $next($request);
    }
}
