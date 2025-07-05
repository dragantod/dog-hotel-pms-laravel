<?php

namespace App\Enums;

enum UserRoles: string
{
    case SUPER_ADMIN = 'super_admin';
    case COMPANY_ADMIN = 'company_admin';
    case SITE_MANAGER = 'site_manager';
    case STAFF = 'staff';
    case VETERINARIAN = 'veterinarian';
    case RECEPTIONIST = 'receptionist';
}