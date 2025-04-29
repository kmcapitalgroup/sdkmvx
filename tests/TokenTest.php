<?php

namespace KmcpG\MultiversxSdkLaravel\Tests;

use Orchestra\Testbench\TestCase;
use KmcpG\MultiversxSdkLaravel\MultiversxServiceProvider;
use PHPUnit\Framework\Attributes\Test;

class TokenTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [MultiversxServiceProvider::class];
    }

    #[Test]
    public function it_can_get_token_properties()
    {
        // TODO: Implement test (likely needs mocking the HTTP client)
        $this->assertTrue(true); // Placeholder test
    }
} 