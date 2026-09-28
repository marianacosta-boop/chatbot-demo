<?php

namespace App\Providers;

use App\Services\Chat\Tools;
use App\Services\Chat\Tools\ToolRegistry;
use App\Services\KnowledgeBase\DbRetriever;
use App\Services\KnowledgeBase\KeywordRetriever;
use App\Services\KnowledgeBase\Retriever;
use App\Services\Salesforce\FakeSalesforceAdapter;
use App\Services\Salesforce\IntegratorSalesforceAdapter;
use App\Services\Salesforce\SalesforceAdapter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Real account via your integrator, fake products/prices. Use FakeSalesforceAdapter::class to run with no integrator at all.
        $this->app->singleton(SalesforceAdapter::class, IntegratorSalesforceAdapter::class);
        $this->app->singleton(Retriever::class, DbRetriever::class);   // KeywordRetriever::class = in-code FAQ, no DB

        $this->app->bind(ToolRegistry::class, fn ($app) => new ToolRegistry([
            $app->make(Tools\SearchKnowledgeBase::class),
            $app->make(Tools\GetRenewalOptions::class),
            $app->make(Tools\CreateRenewalOpportunity::class),
            $app->make(Tools\EscalateToHuman::class),
        ]));
    }

    public function boot(): void {}
}