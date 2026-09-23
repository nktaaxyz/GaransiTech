<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Vendor;
use App\Models\Warranty;
use App\Models\Warranty_claims;
use App\Observers\ActivityLogObserver;

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
        RateLimiter::for('login', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        foreach ([
            Customer::class,
            Vendor::class,
            Product::class,
            ProductUnit::class,
            Warranty::class,
            Warranty_claims::class,
        ] as $model) {
            $model::observe(ActivityLogObserver::class);
        }
    }
}
