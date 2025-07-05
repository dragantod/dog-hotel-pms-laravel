<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    private int       $id;
    private string    $name;
    private string    $legal_name;
    private string    $slug;
    private \DateTime $created_at;
    private \DateTime $updated_at;

    public function __construct($company)
    {
        parent::__construct($company);

        $this->id         = $company->id;
        $this->name       = $company->name;
        $this->legal_name = $company->legal_name;
        $this->slug       = $company->slug;
        $this->created_at = $company->created_at;
        $this->updated_at = $company->updated_at;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'legal_name' => $this->legal_name,
            'slug'       => $this->slug,
            'created_at' => $this->created_at?->format('c'),
            'updated_at' => $this->updated_at?->format('c'),
        ];
    }
} 