<?php

namespace App\Services\UserSite;

use App\DataTransferObjects\UserSiteData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateUserSiteService
{
    public function execute(UserSiteData $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::findOrFail($data->user_id);
            $site = $data->getSite();

            // Double-check company matching at service level for extra security
            if ($user->company_id !== $site->company_id) {
                throw new \InvalidArgumentException('User and site must belong to the same company.');
            }

            // Attach the site to the user
            $user->sites()->attach($data->site_id);

            // Return the user with the newly attached site
            return $user->load('sites');
        });
    }
} 