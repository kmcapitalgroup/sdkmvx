<?php

namespace KmcpG\MultiversxSdkLaravel;

use Illuminate\Support\ServiceProvider;
use KmcpG\MultiversxSdkLaravel\Contracts\MultiversxInterface;
use KmcpG\MultiversxSdkLaravel\Services\WalletService; // Service principal par défaut
use KmcpG\MultiversxSdkLaravel\Services\MultiversxClient;
use KmcpG\MultiversxSdkLaravel\Services\TransactionService;
use KmcpG\MultiversxSdkLaravel\Services\TokenService;

class MultiversxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fusionner la configuration
        $this->mergeConfigFrom(
            __DIR__.'/Config/multiversx.php', 'multiversx'
        );

        // Lier le client HTTP en singleton
        $this->app->singleton(MultiversxClient::class, function ($app) {
            return new MultiversxClient();
        });

        // Lier les services spécifiques
        $this->app->singleton(WalletService::class, function ($app) {
            return new WalletService($app->make(MultiversxClient::class));
        });

        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService($app->make(MultiversxClient::class));
        });

        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService($app->make(MultiversxClient::class));
        });

        // Lier l'interface principale (par défaut au WalletService, peut être adapté)
        $this->app->bind(MultiversxInterface::class, WalletService::class);

        // Alias pour la façade
        $this->app->alias(MultiversxInterface::class, 'multiversx');
    }

    public function boot(): void
    {
        // Rendre la configuration publiable
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