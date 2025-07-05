<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\User\GetUserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly GetUserService $getUserService,
    ) {}

    public function me(Request $request): UserResource
    {
        $user = $this->getUserService->execute($request->user());
        
        return new UserResource($user);
    }
} 