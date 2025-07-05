<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSiteResource extends JsonResource
{
    private int        $user_id;
    private string     $user_name;
    private string     $user_email;
    private int        $site_id;
    private string     $site_name;
    private string     $site_address;
    private int        $company_id;
    private string     $company_name;
    private \DateTime  $created_at;
    private \DateTime  $updated_at;

    public function __construct($user)
    {
        parent::__construct($user);

        $this->user_id      = $user->id;
        $this->user_name    = $user->name;
        $this->user_email   = $user->email;
        $this->company_id   = $user->company_id;
        $this->created_at   = $user->created_at;
        $this->updated_at   = $user->updated_at;

        // For pivot data, we'll handle sites in the toArray method
    }

    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id'         => $this->user_id,
                'name'       => $this->user_name,
                'email'      => $this->user_email,
                'company_id' => $this->company_id,
                'created_at' => $this->created_at?->format('c'),
                'updated_at' => $this->updated_at?->format('c'),
            ],
            'sites' => SiteResource::collection($this->whenLoaded('sites')),
            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }
} 