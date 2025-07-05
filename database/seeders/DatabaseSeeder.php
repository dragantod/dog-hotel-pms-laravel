<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Company;
use App\Enums\UserRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
        ]);

        $site = Site::create([
            'name' => 'Test Site',
            'address' => 'Hercegovacka 12',
            'city' => 'Podgorica',
            'postal_code' => '81001',
            'country_code' => 'ME',
            'timezone' => 'Europe/Podgorica',
            'company_id' => $company->id,
        ]);

        $user->sites()->attach($site->id);

        $this->call([
            RoleAndPermissionSeeder::class,
        ]);

        $user->assignRole(UserRoles::SUPER_ADMIN->value);
    }
}
