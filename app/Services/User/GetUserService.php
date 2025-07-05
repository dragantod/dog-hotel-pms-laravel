<?php

namespace App\Services\User;

use App\Models\User;

class GetUserService
{
    public function execute(User $user): User
    {
        // Load relationships that might be needed
        return $user->load(['company', 'sites', 'roles']);
    }
} 