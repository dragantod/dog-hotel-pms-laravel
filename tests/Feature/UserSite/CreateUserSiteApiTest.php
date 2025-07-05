<?php

namespace Tests\Feature\UserSite;

use App\Models\Company;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserSiteApiTest extends TestCase
{
    use RefreshDatabase;

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
        $this->user = User::factory()->create(['company_id' => $this->company1->id]);
        $this->userFromDifferentCompany = User::factory()->create(['company_id' => $this->company2->id]);

        // Create sites belonging to different companies
        $this->site = Site::factory()->create(['company_id' => $this->company1->id]);
        $this->siteFromDifferentCompany = Site::factory()->create(['company_id' => $this->company2->id]);

        $this->actingAs($this->user);
    }

    public function test_can_assign_site_to_user_with_same_company(): void
    {
        $data = [
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
        ];

        $response = $this->postJson('/v1/user-sites', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'company_id',
                    'created_at',
                    'updated_at',
                ],
                'sites',
                'company',
            ]);

        // Verify the relationship was created in the database
        $this->assertDatabaseHas('user_site', [
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
        ]);
    }

    public function test_cannot_assign_site_to_user_with_different_company(): void
    {
        $data = [
            'user_id' => $this->user->id,
            'site_id' => $this->siteFromDifferentCompany->id,
        ];

        $response = $this->postJson('/v1/user-sites', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['site_id'])
            ->assertJsonFragment([
                'site_id' => ['The selected site must belong to the same company as the user.']
            ]);

        // Verify the relationship was NOT created in the database
        $this->assertDatabaseMissing('user_site', [
            'user_id' => $this->user->id,
            'site_id' => $this->siteFromDifferentCompany->id,
        ]);
    }

    public function test_cannot_assign_user_from_different_company_to_site(): void
    {
        $data = [
            'user_id' => $this->userFromDifferentCompany->id,
            'site_id' => $this->site->id,
        ];

        $response = $this->postJson('/v1/user-sites', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['site_id'])
            ->assertJsonFragment([
                'site_id' => ['The selected site must belong to the same company as the user.']
            ]);

        // Verify the relationship was NOT created in the database
        $this->assertDatabaseMissing('user_site', [
            'user_id' => $this->userFromDifferentCompany->id,
            'site_id' => $this->site->id,
        ]);
    }

    public function test_cannot_assign_same_site_to_user_twice(): void
    {
        // First assignment should succeed
        $this->user->sites()->attach($this->site->id);

        $data = [
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
        ];

        $response = $this->postJson('/v1/user-sites', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['site_id'])
            ->assertJsonFragment([
                'site_id' => ['The user is already assigned to this site.']
            ]);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/v1/user-sites', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'site_id']);
    }

    public function test_validates_user_exists(): void
    {
        $data = [
            'user_id' => 999999, // Non-existent user
            'site_id' => $this->site->id,
        ];

        $response = $this->postJson('/v1/user-sites', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_validates_site_exists(): void
    {
        $data = [
            'user_id' => $this->user->id,
            'site_id' => 999999, // Non-existent site
        ];

        $response = $this->postJson('/v1/user-sites', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['site_id']);
    }

    public function test_can_remove_site_from_user(): void
    {
        // First assign the site
        $this->user->sites()->attach($this->site->id);

        $response = $this->deleteJson("/v1/users/{$this->user->id}/sites/{$this->site->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Site removed from user successfully'
            ]);

        // Verify the relationship was removed from the database
        $this->assertDatabaseMissing('user_site', [
            'user_id' => $this->user->id,
            'site_id' => $this->site->id,
        ]);
    }

    public function test_can_list_user_sites(): void
    {
        // Assign multiple sites to user
        $this->user->sites()->attach([$this->site->id]);
        
        $response = $this->getJson("/v1/users/{$this->user->id}/sites");

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