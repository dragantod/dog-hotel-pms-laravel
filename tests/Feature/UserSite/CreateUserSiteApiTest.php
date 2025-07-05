<?php

namespace Tests\Feature\UserSite;

use App\Enums\UserRoles;
use App\Models\Company;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserSiteApiTest extends TestCase
{
    private User $user;
    private User $userFromDifferentCompany;
    private Site $site;
    private Site $siteFromDifferentCompany;
    private Company $company1;
    private Company $company2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two different companies
        $this->company1 = Company::factory()->create(['name' => 'Company 1']);
        $this->company2 = Company::factory()->create(['name' => 'Company 2']);

        // Create users belonging to different companies
        $this->user = $this->createUser(['company_id' => $this->company1->id]);
        $this->userFromDifferentCompany = $this->createUser(['company_id' => $this->company2->id]);

        // Create sites belonging to different companies
        $this->site = Site::factory()->forCompany($this->company1)->create();
        $this->siteFromDifferentCompany = Site::factory()->forCompany($this->company2)->create();

        $this->user->assignRole(UserRoles::COMPANY_ADMIN->value);
        $this->userFromDifferentCompany->assignRole(UserRoles::COMPANY_ADMIN->value);

        $this->actingAs($this->user);
    }

    public function test_can_list_user_sites(): void
    {
        // Assign multiple sites to user
        $this->user->sites()->attach([$this->site->id]);
        
        $response = $this->getJson("/api/v1/users/{$this->user->id}/sites");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'company_id',
                ],
                'sites' => [
                    '*' => [
                        'id',
                        'name',
                        'address',
                        'city',
                        'company_id',
                    ]
                ]
            ]);
    }
} 