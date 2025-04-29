<?php

namespace KmcpG\MultiversxSdkLaravel\Tests;

use Orchestra\Testbench\TestCase;
use KmcpG\MultiversxSdkLaravel\MultiversxServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use KmcpG\MultiversxSdkLaravel\Services\TokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class TokenTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [MultiversxServiceProvider::class];
    }

    #[Test]
    public function it_can_get_esdt_token_properties()
    {
        $apiUrl = 'https://devnet-api.multiversx.com';
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TokenService $tokenService */
        $tokenService = $this->app->make(TokenService::class);

        $tokenIdentifier = 'WEGLD-bd4d79';

        $fakeTokenData = [
            'identifier' => $tokenIdentifier,
            'name' => 'WrappedEGLD',
            'ticker' => 'WEGLD',
            'owner' => 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqylllslp4zm69',
            'decimals' => 18,
            'isPaused' => false,
            'canUpgrade' => true,
        ];

        Http::fake([
            rtrim($apiUrl, '/') . '/tokens/' . $tokenIdentifier => Http::response($fakeTokenData, 200),
        ]);

        $tokenProperties = $tokenService->getTokenProperties($tokenIdentifier);

        Http::assertSent(function ($request) use ($apiUrl, $tokenIdentifier) {
            return $request->url() === rtrim($apiUrl, '/') . '/tokens/' . $tokenIdentifier &&
                   $request->method() === 'GET';
        });

        $this->assertIsArray($tokenProperties);
        $this->assertEquals($fakeTokenData, $tokenProperties);
        $this->assertEquals($tokenIdentifier, $tokenProperties['identifier']);
        $this->assertEquals('WrappedEGLD', $tokenProperties['name']);
    }

    #[Test]
    public function it_throws_exception_when_getting_properties_for_invalid_token()
    {
        $apiUrl = 'https://devnet-api.multiversx.com';
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TokenService $tokenService */
        $tokenService = $this->app->make(TokenService::class);

        $invalidIdentifier = 'INVALIDTOKEN-123456';

        // Mock an API failure response
        Http::fake([
            rtrim($apiUrl, '/') . '/tokens/' . $invalidIdentifier => Http::response(['message' => 'token not found'], 404),
        ]);

        // Verify that the expected exception is thrown
        $this->expectException(\KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException::class);
        $this->expectExceptionMessage('failed with status 404');

        // Attempt to get properties for the invalid token
        try {
            $tokenService->getTokenProperties($invalidIdentifier);
        } catch (\KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException $e) {
            // Verify that the HTTP call was attempted
            Http::assertSent(function ($request) use ($apiUrl, $invalidIdentifier) {
                return $request->url() === rtrim($apiUrl, '/') . '/tokens/' . $invalidIdentifier &&
                       $request->method() === 'GET';
            });
            // Re-throw the exception
            throw $e;
        }
    }

    #[Test]
    public function it_can_get_token_balance_for_address()
    {
        $apiUrl = 'https://devnet-api.multiversx.com';
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TokenService $tokenService */
        $tokenService = $this->app->make(TokenService::class);

        // Utiliser une adresse devnet valide de 62 caractères
        $address = 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu'; // Adresse devnet valide (62 chars)
        $tokenIdentifier = 'WEGLD-bd4d79'; // Test token

        // --- Add validation inside the test ---
        $this->assertTrue(str_starts_with($address, 'erd1'), "Test address should start with erd1");
        $this->assertEquals(62, strlen($address), "Test address should have 62 chars");
        // --- End validation ---

        // Simulated API response data
        $fakeBalanceData = [
            'identifier' => $tokenIdentifier,
            'balance' => '500000000000000000', // 0.5 WEGLD
            'nonce' => 0, // Nonce pour les SFT/NFT, peut être 0 pour ESDT
            // ... autres champs possibles comme les attributs pour NFT
        ];

        // Mocker l'appel HTTP GET
        $endpoint = "/accounts/{$address}/tokens/{$tokenIdentifier}";
        Http::fake([
            rtrim($apiUrl, '/') . $endpoint => Http::response($fakeBalanceData, 200),
        ]);

        // Appeler la méthode (à créer)
        $balanceInfo = $tokenService->getTokenBalance($address, $tokenIdentifier);

        // Vérifier que l'appel HTTP a été fait
        Http::assertSent(function ($request) use ($apiUrl, $endpoint) {
            return $request->url() === rtrim($apiUrl, '/') . $endpoint &&
                   $request->method() === 'GET';
        });

        // Vérifier les données retournées
        $this->assertIsArray($balanceInfo);
        $this->assertEquals($fakeBalanceData, $balanceInfo);
        $this->assertEquals($tokenIdentifier, $balanceInfo['identifier']);
        $this->assertEquals('500000000000000000', $balanceInfo['balance']);
    }

    #[Test]
    public function it_throws_exception_when_getting_balance_for_invalid_address_or_token()
    {
        $apiUrl = 'https://devnet-api.multiversx.com';
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TokenService $tokenService */
        $tokenService = $this->app->make(TokenService::class);

        $address = 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu'; // Adresse valide
        $nonExistentToken = 'NONEXISTENT-abcdef'; // Token invalide

        // Mock an API failure response
        $endpoint = "/accounts/{$address}/tokens/{$nonExistentToken}";
        Http::fake([
            rtrim($apiUrl, '/') . $endpoint => Http::response(['message' => 'token balance not found'], 404),
        ]);

        // Verify that the expected exception is thrown
        $this->expectException(\KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException::class);
        $this->expectExceptionMessage('failed with status 404');

        // Attempt to get the balance
        try {
            $tokenService->getTokenBalance($address, $nonExistentToken);
        } catch (\KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException $e) {
            // Verify that the HTTP call was attempted
            Http::assertSent(function ($request) use ($apiUrl, $endpoint) {
                return $request->url() === rtrim($apiUrl, '/') . $endpoint &&
                       $request->method() === 'GET';
            });
            // Re-throw the exception
            throw $e;
        }
    }

    #[Test]
    public function it_can_list_tokens_for_account()
    {
        $apiUrl = 'https://devnet-api.multiversx.com';
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TokenService $tokenService */
        $tokenService = $this->app->make(TokenService::class);

        $address = 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu'; // Test address

        // Simulated API response data (array of token balances)
        $fakeTokensList = [
            [
                'identifier' => 'WEGLD-bd4d79',
                'balance' => '500000000000000000',
                'name' => 'WrappedEGLD',
                'ticker' => 'WEGLD',
            ],
            [
                'identifier' => 'USDC-c76f1f',
                'balance' => '100000000', // 100 USDC (6 decimals)
                'name' => 'USD Coin',
                'ticker' => 'USDC',
                'decimals' => 6,
            ],
            // ... potentially more tokens
        ];

        // Mock the HTTP GET call
        $endpoint = "/accounts/{$address}/tokens";
        Http::fake([
            rtrim($apiUrl, '/') . $endpoint => Http::response($fakeTokensList, 200),
        ]);

        // Call the method (to be created)
        $accountTokens = $tokenService->listAccountTokens($address);

        // Verify the HTTP call was made
        Http::assertSent(function ($request) use ($apiUrl, $endpoint) {
            return $request->url() === rtrim($apiUrl, '/') . $endpoint &&
                   $request->method() === 'GET';
        });

        // Verify the returned data
        $this->assertIsArray($accountTokens);
        $this->assertEquals($fakeTokensList, $accountTokens);
        $this->assertCount(2, $accountTokens); // Based on our fake data
        $this->assertEquals('WEGLD-bd4d79', $accountTokens[0]['identifier']);
        $this->assertEquals('USDC-c76f1f', $accountTokens[1]['identifier']);
    }

    #[Test]
    public function it_can_list_tokens_for_account_with_pagination()
    {
        $apiUrl = 'https://devnet-api.multiversx.com';
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TokenService $tokenService */
        $tokenService = $this->app->make(TokenService::class);

        $address = 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu';
        $from = 10;
        $size = 5;

        // Mock API response (content doesn't matter much here, focus is on the URL)
        $fakeTokensList = [/* ... some fake token data ... */];

        // Mock the HTTP GET call with expected query parameters
        $endpoint = "/accounts/{$address}/tokens";
        $expectedUrl = rtrim($apiUrl, '/') . $endpoint . "?from={$from}&size={$size}";

        Http::fake([
            $expectedUrl => Http::response($fakeTokensList, 200),
        ]);

        // Call the method with pagination parameters
        $accountTokens = $tokenService->listAccountTokens($address, $from, $size);

        // Verify the HTTP call was made with the correct URL + query string
        Http::assertSent(function ($request) use ($expectedUrl) {
            return $request->url() === $expectedUrl && $request->method() === 'GET';
        });

        // Basic check on result
        $this->assertIsArray($accountTokens);
    }
} 