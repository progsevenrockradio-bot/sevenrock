<?php

namespace App\Providers;

use App\Contracts\ArchiveOrgPodcastServiceContract;
use App\Services\Gateways\MercadoPagoGateway;
use App\Services\Gateways\PayPalGateway;
use App\Services\Gateways\StripeGateway;
use App\Services\PaymentManager;
use App\Services\ArchiveOrgPodcastService;
use App\Models\ThemeSetting;
use App\Models\RadioProgram;
use App\Observers\RadioProgramObserver;
use App\Support\ThemeAppearance;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ArchiveOrgPodcastServiceContract::class, ArchiveOrgPodcastService::class);

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

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->input('email') ?: $request->ip());
        });

        RateLimiter::for('contact-form', function (Request $request) {
            $key = strtolower(trim((string) $request->input('email', 'guest')));

            return Limit::perMinute(10)->by($request->ip() . '|' . $key);
        });

        RateLimiter::for('comment-submit', function (Request $request) {
            $postId = (string) ($request->route('post')?->id ?? $request->route('post') ?? '0');
            $userId = (string) ($request->user()?->id ?? 'guest');

            return Limit::perMinute(10)->by($request->ip() . '|' . $userId . '|' . $postId);
        });

        RateLimiter::for('public-search', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('admin-actions', function (Request $request) {
            $user = $request->user();
            return $user
                ? Limit::perMinute(60)->by($user->id)
                : Limit::perMinute(15)->by($request->ip());
        });

        if (is_file(app_path('helpers.php'))) {
            require_once app_path('helpers.php');
        }

        RadioProgram::observe(RadioProgramObserver::class);

        View::share('themeSettings', ThemeSetting::current());
        View::share('themeAppearance', ThemeAppearance::resolved());
        View::share('admin', ThemeAppearance::resolved()['admin_texts'] ?? []);

        // Dynamic check for scheduled posts (fallback if cron is not running)
        if (! $this->app->runningInConsole() && ! cache()->has('posts.schedule_check')) {
            cache()->put('posts.schedule_check', true, now()->addMinute());
            try {
                \Illuminate\Support\Facades\Artisan::call('posts:publish-scheduled');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Dynamic scheduled post publish failed: ' . $e->getMessage());
            }
        }
    }
}
