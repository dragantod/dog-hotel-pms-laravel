<?php

namespace App\DataTransferObjects;

use App\Models\Site;
use App\Models\User;

readonly class UserSiteData
{
    public function __construct(
        public int $user_id,
        public int $site_id,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            user_id: $data['user_id'],
            site_id: $data['site_id'],
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: $data['user_id'],
            site_id: $data['site_id'],
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'site_id' => $this->site_id,
        ];
    }

    public function getUser(): User
    {
        return User::findOrFail($this->user_id);
    }

    public function getSite(): Site
    {
        return Site::findOrFail($this->site_id);
    }
} 