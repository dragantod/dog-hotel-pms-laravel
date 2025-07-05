<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view users', $user->company_id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Users can view other users in their company if they have permission
        return $user->company_id === $model->company_id && 
               $user->hasPermissionTo('view users', $user->company_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create users', $user->company_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Users can update other users in their company if they have permission
        return $user->company_id === $model->company_id && 
               $user->hasPermissionTo('update users', $user->company_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Users can delete other users in their company if they have permission
        return $user->company_id === $model->company_id && 
               $user->hasPermissionTo('delete users', $user->company_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id && 
               $user->hasPermissionTo('restore users', $user->company_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->company_id === $model->company_id && 
               $user->hasPermissionTo('force delete users', $user->company_id);
    }
} 