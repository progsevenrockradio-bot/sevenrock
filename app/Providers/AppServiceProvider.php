<?php

namespace App\Providers;

use App\Models\ThemeSetting;
use App\Support\ThemeAppearance;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        if (is_file(app_path('helpers.php'))) {
            require_once app_path('helpers.php');
        }

        View::share('themeSettings', ThemeSetting::current());
        View::share('themeAppearance', ThemeAppearance::resolved());
    }
}
