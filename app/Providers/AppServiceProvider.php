<?php

namespace App\Providers;

use App\Contracts\LlmService;
use App\Services\Llm\OllamaLlmService;
use App\Services\Llm\OpenAiLlmService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmService::class, function () {
            return match (config('llm.driver')) {
                'openai' => new OpenAiLlmService(),
                default => new OllamaLlmService(),
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
