<?php

namespace Btive\ExamBrowser\Laravel;

use Btive\ExamBrowser\Introspector;
use Btive\ExamBrowser\Verifier;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class ExamBrowserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/exam-browser.php', 'exam-browser');

        $this->app->singleton(Introspector::class, fn ($app) => new HttpIntrospector(
            $app['http'] ?? $app->make(\Illuminate\Http\Client\Factory::class),
            $app['cache.store'],
            $app['encrypter'],
            (string) config('exam-browser.console_url'),
            (string) config('exam-browser.api_key'),
            (int) config('exam-browser.cache_ttl'),
            (float) config('exam-browser.timeout'),
        ));

        $this->app->singleton(Verifier::class, fn ($app) => new Verifier($app->make(Introspector::class)));

        $this->app->singleton(ExamBrowserClient::class, fn ($app) => new ExamBrowserClient(
            $app->make(\Illuminate\Http\Client\Factory::class),
            $app->make(Introspector::class),
            (string) config('exam-browser.console_url'),
            (string) config('exam-browser.api_key'),
            (float) config('exam-browser.timeout'),
        ));
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('eb', EnsureExamBrowser::class);
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'exam-browser');

        $this->publishes([__DIR__.'/../../config/exam-browser.php' => config_path('exam-browser.php')], 'exam-browser-config');
        $this->publishes([__DIR__.'/../../resources/views' => resource_path('views/vendor/exam-browser')], 'exam-browser-views');
    }
}
