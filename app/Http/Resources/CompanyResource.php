<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    private int       $id;
    private string    $name;
    private ?string   $email;
    private ?string   $address;
    private ?string   $tax_id;
    private \DateTime $created_at;
    private \DateTime $updated_at;

    public function __construct($company)
    {
        parent::__construct($company);

        $this->id         = $company->id;
        $this->name       = $company->name;
        $this->email      = $company->email;
        $this->address    = $company->address;
        $this->tax_id     = $company->tax_id;
        $this->created_at = $company->created_at;
        $this->updated_at = $company->updated_at;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'address'    => $this->address,
            'tax_id'     => $this->tax_id,
            'created_at' => $this->created_at?->format('c'),
            'updated_at' => $this->updated_at?->format('c'),
        ];
    }
} 