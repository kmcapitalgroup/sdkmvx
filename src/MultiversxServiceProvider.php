<?php

namespace KmcpG\MultiversxSdkLaravel;

use Illuminate\Support\ServiceProvider;
use KmcpG\MultiversxSdkLaravel\Contracts\MultiversxInterface;
use KmcpG\MultiversxSdkLaravel\Services\WalletService; // Default primary service
use KmcpG\MultiversxSdkLaravel\Services\MultiversxClient;
use KmcpG\MultiversxSdkLaravel\Services\TransactionService;
use KmcpG\MultiversxSdkLaravel\Services\TokenService;

class MultiversxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/Config/multiversx.php', 'multiversx'
        );

        // Bind HTTP client as singleton
        $this->app->singleton(MultiversxClient::class, function ($app) {
            return new MultiversxClient();
        });

        // Bind specific services
        $this->app->singleton(WalletService::class, function ($app) {
            return new WalletService($app->make(MultiversxClient::class));
        });

        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService($app->make(MultiversxClient::class));
        });

        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService($app->make(MultiversxClient::class));
        });

        // Bind the main interface (defaults to WalletService, can be adapted)
        $this->app->bind(MultiversxInterface::class, WalletService::class);

        // Alias for the facade
        $this->app->alias(MultiversxInterface::class, 'multiversx');
    }

    public function boot(): void
    {
        // Make configuration publishable
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/Config/multiversx.php' => config_path('multiversx.php'),
            ], 'multiversx-config');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            MultiversxInterface::class,
            MultiversxClient::class,
            WalletService::class,
            TransactionService::class,
            TokenService::class,
            'multiversx',
        ];
    }
} 