<?php

namespace App\Providers;

use App\Contracts\AIImport\QuestionAiService;
use App\Contracts\PastPaperCollector\OcrProvider;
use App\Contracts\PastPaperCollector\QuestionProcessingProvider;
use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AIImport;
use App\Models\AIPaperCollection;
use App\Policies\AIImportPolicy;
use App\Policies\AIPaperCollectionPolicy;
use App\Services\AIImport\AiProviderResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderResolver::class);

        $this->app->bind(WebSearchProvider::class, function ($app) {
            return $app->make(AiProviderResolver::class)->webSearchProvider();
        });

        $this->app->bind(QuestionProcessingProvider::class, function ($app) {
            return $app->make(AiProviderResolver::class)->questionProcessingProvider();
        });

        $this->app->bind(OcrProvider::class, function ($app) {
            return $app->make(AiProviderResolver::class)->ocrProvider();
        });

        $this->app->bind(QuestionAiService::class, function ($app) {
            return $app->make(AiProviderResolver::class)->questionAiService();
        });
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::policy(AIImport::class, AIImportPolicy::class);
        Gate::policy(AIPaperCollection::class, AIPaperCollectionPolicy::class);
    }
}
