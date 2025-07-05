<?php

namespace Database\Seeders;

use App\Enums\UserRoles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions for Dog Hotel PMS
        $permissions = [
            // Company/Site Management
            'view-companies',
            'create-companies',
            'edit-companies',
            'delete-companies',
            'view-sites',
            'create-sites',
            'edit-sites',
            'delete-sites',

            // User Management
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            'assign-roles',

            // Dog Management
            'view-dogs',
            'create-dogs',
            'edit-dogs',
            'delete-dogs',
            'view-dog-medical-records',
            'edit-dog-medical-records',

            // Owner Management
            'view-owners',
            'create-owners',
            'edit-owners',
            'delete-owners',

            // Booking Management
            'view-bookings',
            'create-bookings',
            'edit-bookings',
            'delete-bookings',
            'check-in-bookings',
            'check-out-bookings',
            'cancel-bookings',

            // Kennel Management
            'view-kennels',
            'create-kennels',
            'edit-kennels',
            'delete-kennels',
            'assign-kennels',

            // Service Management
            'view-services',
            'create-services',
            'edit-services',
            'delete-services',
            'assign-services',

            // Calendar Management
            'view-calendar',
            'create-calendar-events',
            'edit-calendar-events',
            'delete-calendar-events',

            // Health & Medical
            'view-health-records',
            'create-health-records',
            'edit-health-records',
            'delete-health-records',
            'view-medical-alerts',
            'create-medical-alerts',

            // Financial Management
            'view-invoices',
            'create-invoices',
            'edit-invoices',
            'delete-invoices',
            'view-payments',
            'process-payments',
            'view-financial-reports',

            // Reporting
            'view-reports',
            'generate-reports',
            'export-reports',

            // Settings
            'view-settings',
            'edit-settings',
            'manage-integrations',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $roles = [
            UserRoles::SUPER_ADMIN->value => $permissions, // All permissions
            UserRoles::COMPANY_ADMIN->value => [
                'view-companies', 'edit-companies',
                'view-sites', 'create-sites', 'edit-sites', 'delete-sites',
                'view-users', 'create-users', 'edit-users', 'delete-users', 'assign-roles',
                'view-dogs', 'create-dogs', 'edit-dogs', 'delete-dogs', 'view-dog-medical-records', 'edit-dog-medical-records',
                'view-owners', 'create-owners', 'edit-owners', 'delete-owners',
                'view-bookings', 'create-bookings', 'edit-bookings', 'delete-bookings', 'check-in-bookings', 'check-out-bookings', 'cancel-bookings',
                'view-kennels', 'create-kennels', 'edit-kennels', 'delete-kennels', 'assign-kennels',
                'view-services', 'create-services', 'edit-services', 'delete-services', 'assign-services',
                'view-calendar', 'create-calendar-events', 'edit-calendar-events', 'delete-calendar-events',
                'view-health-records', 'create-health-records', 'edit-health-records', 'delete-health-records', 'view-medical-alerts', 'create-medical-alerts',
                'view-invoices', 'create-invoices', 'edit-invoices', 'delete-invoices', 'view-payments', 'process-payments', 'view-financial-reports',
                'view-reports', 'generate-reports', 'export-reports',
                'view-settings', 'edit-settings', 'manage-integrations',
            ],
            UserRoles::SITE_MANAGER->value => [
                'view-sites',
                'view-users', 'create-users', 'edit-users',
                'view-dogs', 'create-dogs', 'edit-dogs', 'view-dog-medical-records', 'edit-dog-medical-records',
                'view-owners', 'create-owners', 'edit-owners',
                'view-bookings', 'create-bookings', 'edit-bookings', 'check-in-bookings', 'check-out-bookings', 'cancel-bookings',
                'view-kennels', 'edit-kennels', 'assign-kennels',
                'view-services', 'edit-services', 'assign-services',
                'view-calendar', 'create-calendar-events', 'edit-calendar-events', 'delete-calendar-events',
                'view-health-records', 'create-health-records', 'edit-health-records', 'view-medical-alerts', 'create-medical-alerts',
                'view-invoices', 'create-invoices', 'edit-invoices', 'view-payments', 'process-payments',
                'view-reports', 'generate-reports',
                'view-settings',
            ],
            UserRoles::STAFF->value => [
                'view-dogs', 'edit-dogs',
                'view-owners', 'edit-owners',
                'view-bookings', 'check-in-bookings', 'check-out-bookings',
                'view-kennels', 'assign-kennels',
                'view-services', 'assign-services',
                'view-calendar', 'create-calendar-events', 'edit-calendar-events',
                'view-health-records', 'create-health-records', 'edit-health-records', 'view-medical-alerts',
                'view-invoices',
            ],
            UserRoles::VETERINARIAN->value => [
                'view-dogs', 'view-dog-medical-records', 'edit-dog-medical-records',
                'view-owners',
                'view-bookings',
                'view-kennels',
                'view-health-records', 'create-health-records', 'edit-health-records', 'delete-health-records',
                'view-medical-alerts', 'create-medical-alerts',
                'view-calendar',
            ],
            UserRoles::RECEPTIONIST->value => [
                'view-dogs', 'create-dogs', 'edit-dogs',
                'view-owners', 'create-owners', 'edit-owners',
                'view-bookings', 'create-bookings', 'edit-bookings', 'check-in-bookings', 'check-out-bookings',
                'view-kennels',
                'view-services', 'assign-services',
                'view-calendar', 'create-calendar-events', 'edit-calendar-events',
                'view-health-records', 'view-medical-alerts',
                'view-invoices', 'create-invoices', 'view-payments', 'process-payments',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::create(['name' => $roleName]);
            $role->givePermissionTo($rolePermissions);
        }
    }
}
