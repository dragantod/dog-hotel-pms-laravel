<?php

namespace App\Services\UserSite;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListUserSitesService
{
    public function execute(User $user): Collection
    {
        return $user->sites()->with('company')->get();
    }
} 