<?php

declare(strict_types=1);

namespace Akumi\Sdk\Laravel;

use Akumi\Sdk\Akumi;
use Akumi\Sdk\Client\Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Akumi client as a singleton from config/akumi.php and publishes
 * that config. Auto-discovered via composer extra.laravel.providers, so it is
 * inert outside Laravel and zero-config inside it (reads AKUMI_API_KEY).
 */
final class AkumiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/akumi.php' => $this->app->configPath('akumi.php'),
            ], 'akumi-config');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/akumi.php', 'akumi');

        $this->app->singleton(Akumi::class, static function (Application $app): Akumi {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('akumi', []);

            return new Akumi(new Config(
                apiKey: (string) ($config['api_key'] ?? ''),
                baseUrl: (string) ($config['base_url'] ?? 'https://api.akumi.cloud'),
            ));
        });
    }
}
