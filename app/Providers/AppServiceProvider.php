<?php

namespace App\Providers;

use App\Services\AIRecognitionService;
use App\Services\Contracts\GestureRecognitionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Integrasi deteksi AI Service (YOLOv8 best.pt) dengan fallback cerdas
        $this->app->singleton(GestureRecognitionService::class, function () {
            return new AIRecognitionService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
