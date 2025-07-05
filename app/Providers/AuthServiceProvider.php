<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Site;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Policies\SitePolicy;
use App\Policies\UserPolicy;
use App\Policies\UserSitePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Company::class => CompanyPolicy::class,
        Site::class => SitePolicy::class,
        // UserSite relationships are handled by UserSitePolicy
        // but don't need a model mapping since it's a pivot relationship
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Register custom gates for UserSite operations
        $this->registerUserSiteGates();
    }

    /**
     * Register custom gates for UserSite operations.
     */
    private function registerUserSiteGates(): void
    {
        // Register gates for UserSite operations that don't have a direct model
        Gate::define('viewUserSites', [UserSitePolicy::class, 'viewUserSites']);
        Gate::define('createUserSite', [UserSitePolicy::class, 'create']);
        Gate::define('attachUserSite', [UserSitePolicy::class, 'attach']);
        Gate::define('detachUserSite', [UserSitePolicy::class, 'detach']);
        Gate::define('updateUserSite', [UserSitePolicy::class, 'update']);
        Gate::define('deleteUserSite', [UserSitePolicy::class, 'delete']);
        Gate::define('assignSelfToSite', [UserSitePolicy::class, 'assignSelf']);
        Gate::define('removeSelfFromSite', [UserSitePolicy::class, 'removeSelf']);
    }
} 