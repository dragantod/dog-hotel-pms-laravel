<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\LoginService;
use App\Services\Auth\LogoutService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginService $loginService,
        private readonly LogoutService $logoutService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = $this->loginService->execute($request->toDto());

            return response()->json([
                'message' => 'Login successful',
                'user' => new UserResource($user),
            ], Response::HTTP_OK);
        } catch (AuthenticationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'email' => ['Invalid credentials provided.']
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logoutService->execute($request);

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }
}
