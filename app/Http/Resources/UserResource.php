<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    private int $id;
    private string $name;
    private string $email;
    private ?int $company_id;
    private \DateTime $created_at;
    private \DateTime $updated_at;

    public function __construct($user)
    {
        parent::__construct($user);

        $this->id = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->company_id = $user->company_id;
        $this->created_at = $user->created_at;
        $this->updated_at = $user->updated_at;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at?->format('c'),
            'updated_at' => $this->updated_at?->format('c'),
            'company' => $this->when($this->relationLoaded('company') && $this->company, function () {
                return new CompanyResource($this->company);
            }),
            'sites' => $this->when($this->relationLoaded('sites'), function () {
                return SiteResource::collection($this->sites);
            }),
            'roles' => $this->when($this->relationLoaded('roles'), function () {
                return $this->roles->pluck('name');
            }),
        ];
    }
} 