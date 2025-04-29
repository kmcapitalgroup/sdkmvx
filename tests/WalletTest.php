<?php

namespace KmcpG\MultiversxSdkLaravel\Tests;

use Orchestra\Testbench\TestCase;
use KmcpG\MultiversxSdkLaravel\MultiversxServiceProvider;
use KmcpG\MultiversxSdkLaravel\Contracts\MultiversxInterface;
use PHPUnit\Framework\Attributes\Test;

class WalletTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [MultiversxServiceProvider::class];
    }

    #[Test]
    public function it_can_create_a_wallet_with_a_valid_bech32_address_format()
    {
        /** @var MultiversxInterface $walletService */
        $walletService = $this->app->make(MultiversxInterface::class);
        $wallet = $walletService->createWallet();

        $this->assertIsObject($wallet);
        $this->assertNotEmpty($wallet->privateKey);
        $this->assertNotEmpty($wallet->publicKey);
        $this->assertNotEmpty($wallet->address);

        $this->assertStringStartsWith('erd1', $wallet->address, "L'adresse devrait commencer par 'erd1'.");
        $this->assertEquals(62, strlen($wallet->address), "L'adresse devrait avoir 62 caractères.");
    }
} 