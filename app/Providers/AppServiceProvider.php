<?php

namespace App\Providers;

use App\Support\Appearance;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Appearance::class, fn (): Appearance => Appearance::fromSettings());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function (ViewContract $view): void {
            $view->with('appearance', $this->app->make(Appearance::class));
        });
    }
}
