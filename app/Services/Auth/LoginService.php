<?php

namespace App\Services\Auth;

use App\DataTransferObjects\Auth\LoginData;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    public function execute(LoginData $data): User
    {
        $user = User::where('email', $data->email)->first();

        if (!Auth::attempt($data->toArray())) {
            throw new AuthenticationException('Invalid credentials provided.');
        }

        return $user;
    }
} 