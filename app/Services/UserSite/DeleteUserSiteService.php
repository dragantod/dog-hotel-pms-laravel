<?php

namespace App\Services\UserSite;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUserSiteService
{
    public function execute(User $user, int $siteId): User
    {
        return DB::transaction(function () use ($user, $siteId) {
            // Detach the site from the user
            $user->sites()->detach($siteId);

            // Return the user with updated sites
            return $user->load('sites');
        });
    }
} 