<?php

namespace App\Providers;

use App\Contracts\PastPaperCollector\OcrProvider;
use App\Contracts\PastPaperCollector\QuestionProcessingProvider;
use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Models\AIImport;
use App\Models\AIPaperCollection;
use App\Policies\AIImportPolicy;
use App\Policies\AIPaperCollectionPolicy;
use App\Services\PastPaperCollector\Providers\GeminiQuestionProcessingProvider;
use App\Services\PastPaperCollector\Providers\GeminiWebSearchProvider;
use App\Services\PastPaperCollector\Providers\UnavailableOcrProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WebSearchProvider::class, GeminiWebSearchProvider::class);
        $this->app->bind(QuestionProcessingProvider::class, GeminiQuestionProcessingProvider::class);
        $this->app->bind(OcrProvider::class, UnavailableOcrProvider::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::policy(AIImport::class, AIImportPolicy::class);
        Gate::policy(AIPaperCollection::class, AIPaperCollectionPolicy::class);
    }
}
