<?php

namespace Tests;

use App\Models\User;
use App\Models\Company;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory([
            'name' => 'Test Company',
            'legal_name' => 'Test Company',
            'slug' => 'test-company',
        ])->create();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function createUser($attributes = []): User
    {
        $allAttributes = array_merge(['company_id' => $this->company->id], $attributes);

        return User::factory()->create($allAttributes);
    }
}
