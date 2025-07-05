<?php

namespace App\Http\Requests\UserSite;

use App\DataTransferObjects\UserSiteData;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization will be handled in the controller
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'site_id' => [
                'required',
                'integer',
                'exists:sites,id',
                function ($attribute, $value, $fail) {
                    $this->validateCompanyMatch($value, $fail);
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.company_match' => 'The selected site must belong to the same company as the user.',
        ];
    }

    public function toDto(): UserSiteData
    {
        return UserSiteData::fromRequest($this->validated());
    }

    private function validateCompanyMatch(int $siteId, \Closure $fail): void
    {
        $userId = $this->input('user_id');
        
        if (!$userId) {
            return; // Let the user_id required validation handle this
        }

        $user = User::find($userId);
        $site = Site::find($siteId);

        if (!$user || !$site) {
            return; // Let the exists validation handle this
        }

        if ($user->company_id !== $site->company_id) {
            $fail('The selected site must belong to the same company as the user.');
        }

        // Also check if the relationship already exists
        if ($user->sites()->where('site_id', $siteId)->exists()) {
            $fail('The user is already assigned to this site.');
        }
    }
} 