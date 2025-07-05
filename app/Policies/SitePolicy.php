<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SitePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view sites', $user->company_id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Site $site): bool
    {
        // Users can view sites in their company
        if ($user->company_id === $site->company_id) {
            return $user->hasPermissionTo('view sites', $user->company_id);
        }

        // Super admins can view any site
        return $user->hasPermissionTo('view all sites', $user->company_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create sites', $user->company_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Site $site): bool
    {
        // Users can update sites in their company if they have permission
        if ($user->company_id === $site->company_id) {
            return $user->hasPermissionTo('update sites', $user->company_id);
        }

        // Super admins can update any site
        return $user->hasPermissionTo('update all sites', $user->company_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Site $site): bool
    {
        // Users can delete sites in their company if they have permission
        if ($user->company_id === $site->company_id) {
            return $user->hasPermissionTo('delete sites', $user->company_id);
        }

        // Super admins can delete any site
        return $user->hasPermissionTo('delete all sites', $user->company_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Site $site): bool
    {
        return $user->company_id === $site->company_id && 
               $user->hasPermissionTo('restore sites', $user->company_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Site $site): bool
    {
        return $user->company_id === $site->company_id && 
               $user->hasPermissionTo('force delete sites', $user->company_id);
    }

    /**
     * Determine whether the user can manage users for the site.
     */
    public function manageUsers(User $user, Site $site): bool
    {
        return $user->company_id === $site->company_id && 
               $user->hasPermissionTo('manage site users', $user->company_id);
    }

    /**
     * Determine whether the user can access the site.
     */
    public function access(User $user, Site $site): bool
    {
        // Users can access sites in their company that they're assigned to
        if ($user->company_id === $site->company_id) {
            return $user->sites()->where('site_id', $site->id)->exists() ||
                   $user->hasPermissionTo('access all sites', $user->company_id);
        }

        return false;
    }
} 