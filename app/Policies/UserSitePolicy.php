<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserSitePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any user-site relationships.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view user sites', $user->company_id);
    }

    /**
     * Determine whether the user can view user-site relationships for a specific user.
     */
    public function viewUserSites(User $user, User $targetUser): bool
    {
        // Users can view their own site assignments
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Users can view site assignments for users in their company if they have permission
        return $user->company_id === $targetUser->company_id && 
               $user->hasPermissionTo('view user sites', $user->company_id);
    }

    /**
     * Determine whether the user can create user-site relationships.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create user sites', $user->company_id);
    }

    /**
     * Determine whether the user can attach a user to a site.
     */
    public function attach(User $user, User $targetUser, Site $site): bool
    {
        // All entities must be in the same company
        if ($user->company_id !== $targetUser->company_id || 
            $user->company_id !== $site->company_id) {
            return false;
        }

        return $user->hasPermissionTo('manage user sites', $user->company_id);
    }

    /**
     * Determine whether the user can detach a user from a site.
     */
    public function detach(User $user, User $targetUser, Site $site): bool
    {
        // All entities must be in the same company
        if ($user->company_id !== $targetUser->company_id || 
            $user->company_id !== $site->company_id) {
            return false;
        }

        return $user->hasPermissionTo('manage user sites', $user->company_id);
    }

    /**
     * Determine whether the user can update user-site relationships.
     */
    public function update(User $user, User $targetUser, Site $site): bool
    {
        // All entities must be in the same company
        if ($user->company_id !== $targetUser->company_id || 
            $user->company_id !== $site->company_id) {
            return false;
        }

        return $user->hasPermissionTo('update user sites', $user->company_id);
    }

    /**
     * Determine whether the user can delete user-site relationships.
     */
    public function delete(User $user, User $targetUser, Site $site): bool
    {
        // All entities must be in the same company
        if ($user->company_id !== $targetUser->company_id || 
            $user->company_id !== $site->company_id) {
            return false;
        }

        return $user->hasPermissionTo('delete user sites', $user->company_id);
    }

    /**
     * Determine whether the user can assign themselves to a site.
     */
    public function assignSelf(User $user, Site $site): bool
    {
        // User and site must be in the same company
        if ($user->company_id !== $site->company_id) {
            return false;
        }

        return $user->hasPermissionTo('assign self to sites', $user->company_id);
    }

    /**
     * Determine whether the user can remove themselves from a site.
     */
    public function removeSelf(User $user, Site $site): bool
    {
        // User and site must be in the same company
        if ($user->company_id !== $site->company_id) {
            return false;
        }

        // Check if user is actually assigned to the site
        if (!$user->sites()->where('site_id', $site->id)->exists()) {
            return false;
        }

        return $user->hasPermissionTo('remove self from sites', $user->company_id);
    }
} 