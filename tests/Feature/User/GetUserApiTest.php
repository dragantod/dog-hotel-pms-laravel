<?php

namespace Tests\Feature\User;

use App\Models\Company;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetUserApiTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->actingAs($this->user);
    }

    public function test_can_get_authenticated_user_info(): void
    {
        $response = $this->getJson('/api/v1/user/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'company_id',
                'created_at',
                'updated_at',
                'company',
                'sites',
                'roles',
            ])
            ->assertJson([
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'company_id' => $this->user->company_id,
            ]);
    }

    public function test_user_info_includes_company_when_loaded(): void
    {
        $company = Company::factory()->create();
        $this->user->update(['company_id' => $company->id]);

        $response = $this->getJson('/api/v1/user/me');

        $response->assertStatus(200)
            ->assertJsonPath('company.id', $company->id)
            ->assertJsonPath('company.name', $company->name)
            ->assertJsonStructure([
                'company' => [
                    'id',
                    'name',
                    'legal_name',
                    'slug',
                    'created_at',
                    'updated_at',
                ]
            ]);
    }

    public function test_user_info_includes_sites_when_loaded(): void
    {
        $site = Site::factory()->forCompany($this->user->company)->create();
        $this->user->sites()->attach($site);

        $response = $this->getJson('/api/v1/user/me');

        $response->assertStatus(200)
            ->assertJsonPath('sites.0.id', $site->id)
            ->assertJsonPath('sites.0.name', $site->name);
    }

    public function test_cannot_get_user_info_without_authentication(): void
    {
        $this->app['auth']->forgetUser();

        $response = $this->getJson('/api/v1/user/me');

        $response->assertStatus(401);
    }
} 