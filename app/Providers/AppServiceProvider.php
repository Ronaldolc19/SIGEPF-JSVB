<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        foreach (array_keys(config('access.roles')) as $role) {
            Gate::define('panel.'.$role, fn (User $user): bool => $user->hasRole($role));
        }

        foreach (config('access.modules') as $module => $definition) {
            foreach ($definition['acciones'] as $action) {
                $code = $module.'.'.$action;
                Gate::define($code, fn (User $user): bool => $user->hasRole('administrador') || $user->hasPermission($code));
            }
        }
    }
}
