<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    private string $token;
    private $user;

    public function __construct($authData)
    {
        parent::__construct($authData);

        $this->token = $authData['token'];
        $this->user = $authData['user'];
    }

    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'token_type' => 'Bearer',
            'user' => new UserResource($this->user),
        ];
    }
} 