<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Resources\UserSiteResource;
use App\Models\User;
use App\Services\UserSite\CreateUserSiteService;
use App\Services\UserSite\DeleteUserSiteService;
use App\Services\UserSite\ListUserSitesService;

class UserSiteController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        private readonly CreateUserSiteService $createUserSiteService,
        private readonly DeleteUserSiteService $deleteUserSiteService,
        private readonly ListUserSitesService  $listUserSitesService,
    ) {}

    /**
     * Display a listing of the user's sites.
     */
    public function index(User $user): UserSiteResource
    {
        $this->authorize('viewUserSites', $user);
        
        return new UserSiteResource(
            $this->listUserSitesService->execute($user)
        );
    }
} 