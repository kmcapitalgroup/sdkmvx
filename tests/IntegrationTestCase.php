<?php

namespace KmcpG\MultiversxSdkLaravel\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use KmcpG\MultiversxSdkLaravel\MultiversxServiceProvider;

/**
 * Base class for Integration Tests.
 * Ensures the service provider is loaded and provides a place for common setup.
 */
abstract class IntegrationTestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [MultiversxServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Potentially set specific config for integration tests here if not using phpunit.xml groups
        // config()->set('multiversx.network', 'devnet');
        // config()->set('multiversx.api_url', 'https://devnet-api.multiversx.com');
    }
} 