<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\View;
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
        // School logo, shared with every view. Drop a file at
        // public/images/logo.{png,jpg,jpeg,svg,webp} to use it everywhere;
        // without one, views fall back to the gold "D" mark.
        View::share('schoolLogo', collect(['png', 'jpg', 'jpeg', 'svg', 'webp'])
            ->map(fn ($ext) => 'images/logo.'.$ext)
            ->first(fn ($path) => file_exists(public_path($path))));

        // The signed-in user's color palette, applied app-wide on every render.
        View::composer('*', function ($view) {
            $view->with('userTheme', auth()->user()?->theme ?: 'evergreen');
        });
    }
}
