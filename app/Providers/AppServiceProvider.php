<?php

namespace App\Providers;

use App\Models\ThemeSetting;
use App\Models\RadioProgram;
use App\Observers\RadioProgramObserver;
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

        RadioProgram::observe(RadioProgramObserver::class);

        View::share('themeSettings', ThemeSetting::current());
        View::share('themeAppearance', ThemeAppearance::resolved());
        View::share('admin', ThemeAppearance::resolved()['admin_texts'] ?? []);
    }
}
