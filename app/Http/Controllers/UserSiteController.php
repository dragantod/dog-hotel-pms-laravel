<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSite\CreateUserSiteRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Resources\UserSiteResource;
use App\Models\User;
use App\Services\UserSite\CreateUserSiteService;
use App\Services\UserSite\DeleteUserSiteService;
use App\Services\UserSite\ListUserSitesService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
        $this->authorize('view', $user);
        
        return new UserSiteResource(
            $this->listUserSitesService->execute($user)
        );
    }

    /**
     * Assign a site to a user.
     */
    public function store(CreateUserSiteRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->validated()['user_id']);
        $this->authorize('update', $user);

        $userWithSites = $this->createUserSiteService->execute($request->toDto());

        return (new UserSiteResource($userWithSites))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a site from a user.
     */
    public function destroy(User $user, int $siteId): JsonResponse
    {
        $this->authorize('update', $user);
        
        $userWithSites = $this->deleteUserSiteService->execute($user, $siteId);

        return response()->json([
            'message' => 'Site removed from user successfully',
            'data' => new UserSiteResource($userWithSites),
        ]);
    }
} 