<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view companies', $user->company_id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Company $company): bool
    {
        // Users can view their own company
        if ($user->company_id === $company->id) {
            return true;
        }

        // Super admins can view any company
        return $user->hasPermissionTo('view all companies', $user->company_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create companies', $user->company_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Company $company): bool
    {
        // Users can update their own company if they have permission
        if ($user->company_id === $company->id) {
            return $user->hasPermissionTo('update company', $user->company_id);
        }

        // Super admins can update any company
        return $user->hasPermissionTo('update all companies', $user->company_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Company $company): bool
    {
        // Users cannot delete their own company
        if ($user->company_id === $company->id) {
            return false;
        }

        // Super admins can delete companies
        return $user->hasPermissionTo('delete companies', $user->company_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('restore companies', $user->company_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('force delete companies', $user->company_id);
    }
} 