<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginApiTest extends TestCase
{
    public function test_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $data = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/login', $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'company_id',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ],
            ]);

        // Check that user is authenticated
        $this->assertAuthenticated();
    }

    public function test_cannot_login_with_invalid_email(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $data = [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/login', $data);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials provided.',
                'errors' => [
                    'email' => ['Invalid credentials provided.']
                ]
            ]);
    }

    public function test_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $data = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ];

        $response = $this->postJson('/api/v1/auth/login', $data);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials provided.',
                'errors' => [
                    'email' => ['Invalid credentials provided.']
                ]
            ]);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_validates_email_format(): void
    {
        $data = [
            'email' => 'invalid-email',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/login', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_validates_password_minimum_length(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => '123',
        ];

        $response = $this->postJson('/api/v1/auth/login', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
} 