<?php

namespace KmcpG\MultiversxSdkLaravel\Tests;

use Orchestra\Testbench\TestCase;
use KmcpG\MultiversxSdkLaravel\MultiversxServiceProvider;
use PHPUnit\Framework\Attributes\Test;

class TransactionTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [MultiversxServiceProvider::class];
    }

    #[Test]
    public function it_can_prepare_a_transaction()
    {
        // TODO: Implement test
        $this->assertTrue(true); // Placeholder test
    }

    #[Test]
    public function it_can_send_a_transaction()
    {
        // TODO: Implement test (likely needs mocking the HTTP client)
        $this->assertTrue(true); // Placeholder test
    }
} 