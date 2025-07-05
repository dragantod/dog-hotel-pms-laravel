<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    private int       $id;
    private string    $name;
    private string    $address;
    private string    $city;
    private ?string   $postal_code;
    private string    $country_code;
    private string    $timezone;
    private ?string   $tax_id;
    private int       $company_id;
    private \DateTime $created_at;
    private \DateTime $updated_at;

    public function __construct($site)
    {
        parent::__construct($site);

        $this->id           = $site->id;
        $this->name         = $site->name;
        $this->address      = $site->address;
        $this->city         = $site->city;
        $this->postal_code  = $site->postal_code;
        $this->country_code = $site->country_code;
        $this->timezone     = $site->timezone;
        $this->tax_id       = $site->tax_id;
        $this->company_id   = $site->company_id;
        $this->created_at   = $site->created_at;
        $this->updated_at   = $site->updated_at;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'address'      => $this->address,
            'city'         => $this->city,
            'postal_code'  => $this->postal_code,
            'country_code' => $this->country_code,
            'timezone'     => $this->timezone,
            'tax_id'       => $this->tax_id,
            'company_id'   => $this->company_id,
            'created_at'   => $this->created_at?->format('c'),
            'updated_at'   => $this->updated_at?->format('c'),
            'company'      => new CompanyResource($this->whenLoaded('company')),
        ];
    }
} 