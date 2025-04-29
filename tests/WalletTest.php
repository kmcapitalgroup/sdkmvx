<?php

namespace KmcpG\MultiversxSdkLaravel\Tests;

use Orchestra\Testbench\TestCase;
use KmcpG\MultiversxSdkLaravel\MultiversxServiceProvider;
use KmcpG\MultiversxSdkLaravel\Contracts\MultiversxInterface;
use PHPUnit\Framework\Attributes\Test;
use KmcpG\MultiversxSdkLaravel\Services\WalletService;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Support\Facades\Http;

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
        $this->assertEquals(62, strlen($wallet->address), "Address should have 62 characters.");
    }

    #[Test]
    public function it_validates_a_correct_address_as_true()
    {
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class); // Need WalletService here

        // Generate a valid address for the test
        $validAddress = $walletService->createWallet()->address;

        $this->assertTrue($walletService->isValidAddress($validAddress), "A valid generated address should be recognized as valid.");
    }

    /**
     * @dataProvider invalidAddressProvider
     */
    #[Test]
    #[DataProvider('invalidAddressProvider')]
    public function it_validates_incorrect_addresses_as_false(string $invalidAddress, string $message)
    {
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);
        $this->assertFalse($walletService->isValidAddress($invalidAddress), $message);
    }

    /**
     * Provides invalid addresses for testing.
     */
    public static function invalidAddressProvider(): array
    {
        return [
            ['erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqylllsl', "Incorrect length (too short)"],
            ['erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqylllsl', "Incorrect length (too long)"],
            ['btc1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqylllsl', "Incorrect prefix (HRP)"],
            ['erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqyllls0', "Invalid checksum"], // Changed last char
            ['erd1invalid-chars*?/', "Invalid characters"],
            ['', "Empty string"],
        ];
    }

    #[Test]
    public function it_can_get_account_details_for_a_valid_address()
    {
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);

        // 1. Valid address (can be fictitious for the test)
        $address = 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqylllslpch8w9'; // Known valid address or generated
        if (! $walletService->isValidAddress($address)) {
            // If the provided address isn't valid, create one
            $address = $walletService->createWallet()->address;
        }

        // 2. Simulated API response data
        $fakeAccountData = [
            'address' => $address,
            'nonce' => 10,
            'balance' => '1000000000000000000', // 1 EGLD
            'code' => null,
            'username' => 'testuser'
        ];

        // 3. Mock the HTTP call
        $apiUrl = rtrim(config('multiversx.api_url', 'https://devnet-api.multiversx.com'), '/'); // Use the config URL
        Http::fake([
            $apiUrl . '/accounts/' . $address => Http::response($fakeAccountData, 200),
        ]);

        // 4. Call the method
        $accountData = $walletService->getAccount($address);

        // 5. Verify the HTTP call was made
        Http::assertSent(function ($request) use ($apiUrl, $address) {
            return $request->url() === $apiUrl . '/accounts/' . $address;
        });

        // 6. Verify the returned data
        $this->assertEquals($fakeAccountData, $accountData);
        $this->assertEquals($address, $accountData['address']);
        $this->assertEquals(10, $accountData['nonce']);
    }

    #[Test]
    public function it_throws_exception_when_getting_account_for_invalid_address()
    {
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);
        $invalidAddress = 'erd1invalidaddress';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid address format: {$invalidAddress}");

        // We don't mock Http::fake() here, as the exception should be thrown before the HTTP call
        $walletService->getAccount($invalidAddress);
    }

    #[Test]
    public function it_throws_exception_when_api_call_fails()
    {
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);
        $address = $walletService->createWallet()->address; // Valid address to pass initial validation

        // Mock an API failure response
        $apiUrl = rtrim(config('multiversx.api_url', 'https://devnet-api.multiversx.com'), '/');
        Http::fake([
            $apiUrl . '/accounts/' . $address => Http::response(['message' => 'Account not found'], 404),
        ]);

        $this->expectException(\KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException::class);
        $this->expectExceptionMessage('failed with status 404'); // Expect substring of the new message

        $walletService->getAccount($address);

        // Verify that the HTTP call was attempted
        Http::assertSent(function ($request) use ($apiUrl, $address) {
            return $request->url() === $apiUrl . '/accounts/' . $address;
        });
    }

    #[Test]
    public function it_can_convert_bech32_address_to_public_key_hex()
    {
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);

        // Generate a wallet to get known pair
        $wallet = $walletService->createWallet();
        $originalAddress = $wallet->address;
        $originalPublicKeyHex = $wallet->publicKey; // This is already the 32-byte hex

        // Convert the address back to public key hex
        $convertedPublicKeyHex = WalletService::bech32ToPublicKeyHex($originalAddress);

        $this->assertEquals(strtolower($originalPublicKeyHex), strtolower($convertedPublicKeyHex), "Converted public key hex should match the original.");
        $this->assertEquals(64, strlen($convertedPublicKeyHex), "Public key hex should be 64 characters long.");
    }

    #[Test]
    public function it_throws_exception_when_converting_invalid_bech32_address()
    {
        $this->expectException(\InvalidArgumentException::class);
        WalletService::bech32ToPublicKeyHex('invalid-address-format');
    }

    #[Test]
    public function it_throws_exception_when_converting_address_with_invalid_checksum()
    {
        $this->expectException(\InvalidArgumentException::class);
        // Valid format, likely invalid checksum
        WalletService::bech32ToPublicKeyHex('erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4h0');
    }

    #[Test]
    public function it_throws_exception_when_converting_address_with_invalid_hrp()
    {
        $this->expectException(\InvalidArgumentException::class);
        // Valid checksum according to bech32, but wrong HRP
        WalletService::bech32ToPublicKeyHex('btc1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu');
    }
} 