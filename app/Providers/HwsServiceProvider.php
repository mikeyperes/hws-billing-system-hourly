<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GenericService;
use App\Services\StripeService;
use App\Services\GoogleSheetsService;
use App\Services\EmailService;
use App\Services\BillingService;

/**
 * HwsServiceProvider — registers all HWS services as singletons.
 * Ensures one instance of each service is shared across the request lifecycle.
 * Register this provider in bootstrap/providers.php (Laravel 11) or config/app.php.
 */
class HwsServiceProvider extends ServiceProvider
{
    /**
     * Register service bindings in the container.
     *
     * @return void
     */
    public function register(): void
    {
        // GenericService is a singleton — shared utility used by all other services
        $this->app->singleton(GenericService::class, function ($app) {
            // No dependencies — just instantiate
            return new GenericService();
        });

        // StripeService is a singleton — wraps all Stripe API calls
        $this->app->singleton(StripeService::class, function ($app) {
            // Depends on GenericService for logging
            return new StripeService(
                $app->make(GenericService::class)
            );
        });

        // GoogleSheetsService is a singleton — wraps all Google Sheets API calls
        $this->app->singleton(GoogleSheetsService::class, function ($app) {
            // Depends on GenericService for logging
            return new GoogleSheetsService(
                $app->make(GenericService::class)
            );
        });

        // EmailService is a singleton — THE single email function for the entire system
        $this->app->singleton(EmailService::class, function ($app) {
            // Depends on GenericService for logging and utility methods
            return new EmailService(
                $app->make(GenericService::class)
            );
        });

        // BillingService is a singleton — orchestrates the billing workflow
        $this->app->singleton(BillingService::class, function ($app) {
            // Depends on all three API services
            return new BillingService(
                $app->make(GoogleSheetsService::class),
                $app->make(StripeService::class),
                $app->make(GenericService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Nothing to bootstrap — services are ready after registration
    }
}
