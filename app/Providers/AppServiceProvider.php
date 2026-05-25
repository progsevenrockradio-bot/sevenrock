<?php

namespace App\Providers;

use App\Services\Gateways\MercadoPagoGateway;
use App\Services\Gateways\PayPalGateway;
use App\Services\Gateways\StripeGateway;
use App\Services\PaymentManager;
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
        $this->app->singleton(PaymentManager::class, function ($app): PaymentManager {
            $manager = new PaymentManager($app);
            $manager->register('stripe', $app->make(StripeGateway::class));
            $manager->register('paypal', $app->make(PayPalGateway::class));
            $manager->register('mercadopago', $app->make(MercadoPagoGateway::class));

            return $manager;
        });
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
