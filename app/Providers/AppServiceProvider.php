<?php

namespace App\Providers;

use App\Pricing\Data\PricingConfig;
use App\Pricing\FixturePortfolioSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PricingConfig::class, fn (): PricingConfig => PricingConfig::fromArray(config()->array('portfolio_pricing')));
        $this->app->singleton(FixturePortfolioSource::class, fn (): FixturePortfolioSource => new FixturePortfolioSource(base_path('fixtures')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
