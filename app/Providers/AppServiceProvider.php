<?php

namespace App\Providers;

use App\Http\Middleware\EnsureCompanyEntitled;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // R-50: Livewire updates from a page in the signed-in area carry that page's entitlement check with them.
        Livewire::addPersistentMiddleware([EnsureCompanyEntitled::class]);

        Gate::define('canCreateForm', fn (User $user, Team $team) => $user->can('canCreateForm', $team));
        Gate::define('canEditForm', fn (User $user, Team $team) => $user->can('canEditForm', $team));
        Gate::define('canViewSubmissions', fn (User $user, Team $team) => $user->can('canViewSubmissions', $team));
    }
}
