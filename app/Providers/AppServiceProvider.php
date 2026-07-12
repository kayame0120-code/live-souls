<?php

namespace App\Providers;

use App\Contracts\LlmService;
use App\Services\Llm\FakeLlmService;
use App\Services\Llm\OpenAiLlmService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmService::class, function () {
            return match (config('llm.driver')) {
                'fake' => new FakeLlmService(),
                default => new OpenAiLlmService(),
            };
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
