<?php

namespace KmcpG\MultiversxSdkLaravel\Tests;

use Orchestra\Testbench\TestCase;
use KmcpG\MultiversxSdkLaravel\MultiversxServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use KmcpG\MultiversxSdkLaravel\Services\TransactionService;
use KmcpG\MultiversxSdkLaravel\Services\MultiversxClient;
use Illuminate\Support\Facades\Config;
use KmcpG\MultiversxSdkLaravel\Services\WalletService;
use Illuminate\Support\Facades\Http;

class TransactionTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [MultiversxServiceProvider::class];
    }

    #[Test]
    public function it_can_prepare_a_basic_transaction_structure()
    {
        Config::set('multiversx.network', 'devnet');
        Config::set('multiversx.api_url', 'https://devnet-api.multiversx.com');

        /** @var TransactionService $transactionService */
        $transactionService = $this->app->make(TransactionService::class);

        $sender = 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu';
        $pingPongContract = 'erd1qqqqqqqqqqqqqpgqhy6nx6zq7dzwezqzdd0lf4sf9vd8cn7qrmcqfmcze2'; // Known devnet contract

        $params = [
            'sender' => $sender,
            'contractAddress' => $pingPongContract, // Use known devnet contract
            'functionName' => 'doNothing',          // Use dummy function name to pass validation
            'arguments' => [],             // No arguments
            'value' => '100000000000000000',
            'nonce' => 15,
            'gasLimit' => 50000,
            'data_payload' => 'hello', // Keep original data payload separately
        ];

        // Prepare using the public method
        $preparedTx = $transactionService->prepareSmartContractCall($params);

        $this->assertIsArray($preparedTx);
        $this->assertEquals($params['nonce'], $preparedTx['nonce']);
        $this->assertEquals($params['value'], $preparedTx['value']);
        $this->assertEquals($params['contractAddress'], $preparedTx['receiver']); // Check receiver is the contract address
        $this->assertEquals($params['sender'], $preparedTx['sender']);
        $this->assertEquals($params['gasLimit'], $preparedTx['gasLimit']);
        // Note: data in prepareSmartContractCall combines function+args, so we can't easily test the original 'hello'
        // We can check that *some* data is generated though
        $this->assertNotEmpty($preparedTx['data']);

        $this->assertArrayHasKey('gasPrice', $preparedTx);
        $this->assertEquals(1000000000, $preparedTx['gasPrice']);
        $this->assertArrayHasKey('chainID', $preparedTx);
        $this->assertEquals('D', $preparedTx['chainID']); // 'D' for Devnet (based on config)
        $this->assertArrayHasKey('version', $preparedTx);
        $this->assertEquals(1, $preparedTx['version']); // Current transaction version
    }

    #[Test]
    public function it_uses_configuration_for_gas_price_and_version()
    {
        $customGasPrice = 2000000000;
        $customVersion = 2;
        Config::set('multiversx.default_gas_price', $customGasPrice);
        Config::set('multiversx.default_tx_version', $customVersion);
        Config::set('multiversx.network', 'testnet'); // Use testnet for a different chainID

        /** @var TransactionService $transactionService */
        $transactionService = $this->app->make(TransactionService::class);

        // We need to call a public prepare method, like prepareSmartContractCall
        // We'll use minimal valid parameters for this test
        $pingPongContract = 'erd1qqqqqqqqqqqqqpgqhy6nx6zq7dzwezqzdd0lf4sf9vd8cn7qrmcqfmcze2'; // Known devnet contract
        $params = [
            'sender' => 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu',
            'contractAddress' => $pingPongContract, // Use known devnet contract
            'functionName' => 'doNothing',
            'arguments' => [],
            'value' => '0',
            'nonce' => 1,
            'gasLimit' => 50000,
        ];

        $preparedTx = $transactionService->prepareSmartContractCall($params);

        $this->assertEquals($customGasPrice, $preparedTx['gasPrice']);
        $this->assertEquals($customVersion, $preparedTx['version']);
        $this->assertEquals('T', $preparedTx['chainID']); // Check if chainID changes with network config
    }

    #[Test]
    public function it_can_sign_a_prepared_transaction()
    {
        Config::set('multiversx.network', 'devnet');
        Config::set('multiversx.api_url', 'https://devnet-api.multiversx.com');

        /** @var TransactionService $transactionService */
        $transactionService = $this->app->make(TransactionService::class);
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class); // To generate a key

        // 1. Generate a test wallet to get a private key
        $testWallet = $walletService->createWallet();
        $privateKeyHex = $testWallet->privateKey;
        $senderAddress = $testWallet->address;
        $pingPongContract = 'erd1qqqqqqqqqqqqqpgqhy6nx6zq7dzwezqzdd0lf4sf9vd8cn7qrmcqfmcze2'; // Known devnet contract

        // 2. Prepare a transaction using a public method (e.g., prepareSmartContractCall for simplicity)
        $params = [
            'sender' => $senderAddress,
            'contractAddress' => $pingPongContract, // Use known devnet contract
            'functionName' => 'doNothing', // Use dummy function name
            'arguments' => [], // Empty for EGLD-like transfer
            'value' => '10000',
            'nonce' => 0,
            'gasLimit' => 50000,
        ];
        $preparedTx = $transactionService->prepareSmartContractCall($params);

        // 3. Sign the transaction (method to be created)
        $signedTx = $transactionService->signTransaction($preparedTx, $privateKeyHex);

        // 4. Check signature presence and format
        $this->assertIsArray($signedTx);
        $this->assertArrayHasKey('signature', $signedTx);
        $this->assertIsString($signedTx['signature']);
        // An ECDSA signature (r + s) is 64 bytes = 128 hex characters
        $this->assertEquals(128, strlen($signedTx['signature']), "Signature should be 128 hex characters long.");
        // Check that other fields are preserved
        $this->assertEquals($preparedTx['nonce'], $signedTx['nonce']);
        $this->assertEquals($preparedTx['receiver'], $signedTx['receiver']);

        // Optional: Verify signature validity (more complex, needs public key and hash)
        // $isValid = $this->verifySignature($signedTx, $testWallet->publicKey);
        // $this->assertTrue($isValid, "Generated signature should be valid.");
    }

    #[Test]
    public function it_can_send_a_signed_transaction()
    {
        Config::set('multiversx.network', 'devnet');
        $apiUrl = 'https://devnet-api.multiversx.com'; // Use a fixed URL for the test
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TransactionService $transactionService */
        $transactionService = $this->app->make(TransactionService::class);
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);

        // 1. Prepare and sign a transaction using public methods
        $testWallet = $walletService->createWallet();
        $pingPongContract = 'erd1qqqqqqqqqqqqqpgqhy6nx6zq7dzwezqzdd0lf4sf9vd8cn7qrmcqfmcze2'; // Known devnet contract
        $params = [
            'sender' => $testWallet->address,
            'contractAddress' => $pingPongContract, // Use known devnet contract
            'functionName' => 'doNothing', // Use dummy function name
            'arguments' => [], // Empty for EGLD-like transfer
            'value' => '10',
            'nonce' => 1,
            'gasLimit' => 50000,
        ];
        $preparedTx = $transactionService->prepareSmartContractCall($params);
        $signedTx = $transactionService->signTransaction($preparedTx, $testWallet->privateKey);

        // 2. Define the simulated API response
        $fakeApiResponse = [
            'txHash' => 'abcdef1234567890' . md5(json_encode($signedTx)), // Dummy but unique hash
        ];

        // 3. Mock the HTTP POST call to /transactions
        Http::fake([
            rtrim($apiUrl, '/') . '/transactions' => Http::response($fakeApiResponse, 200),
        ]);

        // 4. Call the send method (to be created)
        $result = $transactionService->sendTransaction($signedTx);

        // 5. Verify the HTTP call was made with the correct data
        Http::assertSent(function ($request) use ($apiUrl, $signedTx) {
            return $request->url() === rtrim($apiUrl, '/') . '/transactions' &&
                   $request->method() === 'POST' &&
                   $request->body() === json_encode($signedTx, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION); // Check the exact payload
        });

        // 6. Verify the returned result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('txHash', $result);
        $this->assertEquals($fakeApiResponse['txHash'], $result['txHash']);
    }

    #[Test]
    public function it_throws_exception_when_sending_transaction_fails()
    {
        Config::set('multiversx.network', 'devnet');
        $apiUrl = 'https://devnet-api.multiversx.com';
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TransactionService $transactionService */
        $transactionService = $this->app->make(TransactionService::class);
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);

        // 1. Prepare and sign a valid transaction using public methods
        $testWallet = $walletService->createWallet();
        $pingPongContract = 'erd1qqqqqqqqqqqqqpgqhy6nx6zq7dzwezqzdd0lf4sf9vd8cn7qrmcqfmcze2'; // Known devnet contract
        $params = [
            'sender' => $testWallet->address,
            'contractAddress' => $pingPongContract, // Use known devnet contract
            'functionName' => 'doNothing', // Use dummy function name
            'arguments' => [],
            'value' => '10',
            'nonce' => 1,
            'gasLimit' => 50000,
        ];
        $preparedTx = $transactionService->prepareSmartContractCall($params);
        $signedTx = $transactionService->signTransaction($preparedTx, $testWallet->privateKey);

        // 2. Define the simulated error response
        $fakeErrorResponse = [
            'statusCode' => 400,
            'message' => 'invalid transaction format', // Dummy error message
        ];

        // 3. Mock the HTTP POST call with an error response
        Http::fake([
            rtrim($apiUrl, '/') . '/transactions' => Http::response($fakeErrorResponse, 400),
        ]);

        // 4. Verify that the expected exception is thrown
        $this->expectException(\KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException::class);
        $this->expectExceptionMessage('failed with status 400'); // Expect substring of the new message

        // 5. Attempt to send the transaction
        try {
            $transactionService->sendTransaction($signedTx);
        } catch (\KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException $e) {
            // 6. Verify that the HTTP call was attempted despite the failure
            Http::assertSent(function ($request) use ($apiUrl, $signedTx) {
                return $request->url() === rtrim($apiUrl, '/') . '/transactions' &&
                    $request->method() === 'POST' &&
                    $request->body() === json_encode($signedTx, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
            });
            // Re-throw the exception for PHPUnit to catch
            throw $e;
        }
    }

    #[Test]
    public function it_can_get_transaction_status()
    {
        $apiUrl = 'https://devnet-api.multiversx.com';
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TransactionService $transactionService */
        $transactionService = $this->app->make(TransactionService::class);

        $txHash = 'abcdef1234567890fedcba0987654321abcdef1234567890fedcba0987654321'; // 64 char hex hash

        // Simulated API response data
        $fakeTxStatusData = [
            'txHash' => $txHash,
            'status' => 'success',
            'isCompleted' => true,
            'sender' => 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu',
            'receiver' => 'erd1qyu5wthldzr8wx5c9ucg8kjagg0jfs53s8nr3zpzaxksxc5xwztqsdle3',
            'value' => '10',
            'gasUsed' => 50000,
            // ... many other fields
        ];

        // Mock the HTTP GET call
        $endpoint = "/transactions/{$txHash}";
        Http::fake([
            rtrim($apiUrl, '/') . $endpoint => Http::response($fakeTxStatusData, 200),
        ]);

        // Call the method (to be created)
        $txStatus = $transactionService->getTransactionStatus($txHash);

        // Verify the HTTP call was made
        Http::assertSent(function ($request) use ($apiUrl, $endpoint) {
            return $request->url() === rtrim($apiUrl, '/') . $endpoint &&
                   $request->method() === 'GET';
        });

        // Verify the returned data
        $this->assertIsArray($txStatus);
        $this->assertEquals($fakeTxStatusData, $txStatus);
        $this->assertEquals($txHash, $txStatus['txHash']);
        $this->assertEquals('success', $txStatus['status']);
        $this->assertTrue($txStatus['isCompleted']);
    }

    #[Test]
    public function it_throws_exception_when_getting_status_for_invalid_hash()
    {
        $apiUrl = 'https://devnet-api.multiversx.com';
        Config::set('multiversx.api_url', $apiUrl);

        /** @var TransactionService $transactionService */
        $transactionService = $this->app->make(TransactionService::class);

        // Use a hash with a valid format (64 hex chars) but assumed non-existent
        $nonExistentTxHash = str_repeat('0', 64);

        // Mock an API failure response
        $endpoint = "/transactions/{$nonExistentTxHash}";
        Http::fake([
            rtrim($apiUrl, '/') . $endpoint => Http::response(['message' => 'transaction not found'], 404),
        ]);

        // Verify that the expected exception is thrown (from API client)
        $this->expectException(\KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException::class);
        $this->expectExceptionMessage('failed with status 404'); // Expect substring of the new message

        // Attempt to get the status
        try {
            $transactionService->getTransactionStatus($nonExistentTxHash);
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
    public function it_can_prepare_an_esdt_transfer_transaction()
    {
        Config::set('multiversx.network', 'devnet');
        $transactionService = $this->app->make(TransactionService::class);

        $sender = 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu';
        $receiver = 'erd1qyu5wthldzr8wx5c9ucg8kjagg0jfs53s8nr3zpzaxksxc5xwztqsdle3';
        $tokenIdentifier = 'WEGLD-bd4d79';
        $amount = gmp_init('150000000000000000'); // 0.15 WEGLD as GMP object
        $nonce = 20;

        // Prepare using a hypothetical new method
        $preparedTx = $transactionService->prepareEsdtTransfer([
            'sender' => $sender,
            'receiver' => $receiver,
            'tokenIdentifier' => $tokenIdentifier,
            'amount' => $amount, // Pass GMP object directly
            'nonce' => $nonce,
            'gasLimit' => 600000, // Gas limit might be higher for ESDT transfers
        ]);

        $this->assertIsArray($preparedTx);
        $this->assertEquals(0, $preparedTx['value'], "Value should be 0 for standard ESDT transfers");
        $this->assertEquals($receiver, $preparedTx['receiver']);
        $this->assertEquals($sender, $preparedTx['sender']);
        $this->assertEquals($nonce, $preparedTx['nonce']);
        $this->assertArrayHasKey('data', $preparedTx);

        // Expected data format: ESDTTransfer@token_hex@amount_hex
        $expectedData = 'ESDTTransfer@' . bin2hex($tokenIdentifier) . '@' . str_pad(gmp_strval($amount, 16), 1, '0', STR_PAD_LEFT); // Amount hex (minimal padding)
        $this->assertEquals(base64_encode($expectedData), $preparedTx['data'], "Data field format is incorrect for ESDT transfer.");

        // Check standard fields are still present
        $this->assertEquals('D', $preparedTx['chainID']);
        $this->assertEquals(1, $preparedTx['version']);
        $this->assertEquals(1000000000, $preparedTx['gasPrice']);
    }

    #[Test]
    public function it_can_prepare_a_smart_contract_call_transaction()
    {
        Config::set('multiversx.network', 'devnet');
        $transactionService = $this->app->make(TransactionService::class);

        $sender = 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu';
        $contractAddress = 'erd1qqqqqqqqqqqqqpgqsu2v43z8ujl4am37fwl3u6e50975k93xwztqztfMCR'; // Example SC address
        $functionName = 'deposit';
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);

        // Generate a valid address within the test
        $generatedWallet = $walletService->createWallet();
        $addressArg = $generatedWallet->address;

        // Use strlen for consistency with the passing wallet test
        $this->assertEquals(62, strlen($addressArg), "Length check inside test for addressArg");
        $arguments = [
            gmp_init(1000), // Argument 1: GMP integer
            $addressArg,    // Argument 2: Address string (Restored)
            true,           // Argument 3: Boolean true (Restored)
            'hello_world'   // Argument 4: string (Restored)
        ];
        $value = '50000000000000000'; // 0.05 EGLD sent with the call
        $nonce = 21;

        // Prepare using a hypothetical new method
        $preparedTx = $transactionService->prepareSmartContractCall([
            'sender' => $sender,
            'contractAddress' => $contractAddress,
            'functionName' => $functionName,
            'arguments' => $arguments, // Pass arguments array
            'value' => $value,
            'nonce' => $nonce,
            'gasLimit' => 5000000, // Gas limit usually higher for SC calls
        ]);

        $this->assertIsArray($preparedTx);
        $this->assertEquals($value, $preparedTx['value']);
        $this->assertEquals($contractAddress, $preparedTx['receiver']);
        $this->assertEquals($sender, $preparedTx['sender']);
        $this->assertEquals($nonce, $preparedTx['nonce']);
        $this->assertArrayHasKey('data', $preparedTx);

        // Expected data format: function@arg1_hex@arg2_hex...
        $arg1Hex = str_pad(gmp_strval($arguments[0], 16), 1, '0', STR_PAD_LEFT);
        // Calculer l'hex attendu pour l'adresse en utilisant la vraie fonction
        $arg2Hex = \KmcpG\MultiversxSdkLaravel\Services\WalletService::bech32ToPublicKeyHex($arguments[1]);
        $arg3Hex = $arguments[2] ? '01' : '00'; // Boolean true/false
        $arg4Hex = bin2hex($arguments[3]);
        $expectedData = $functionName . '@' . $arg1Hex . '@' . $arg2Hex . '@' . $arg3Hex . '@' . $arg4Hex;

        $this->assertEquals(base64_encode($expectedData), $preparedTx['data'], "Data field format is incorrect for SC call.");

        // Check standard fields
        $this->assertEquals('D', $preparedTx['chainID']);
        $this->assertEquals(1, $preparedTx['version']);
        $this->assertEquals(1000000000, $preparedTx['gasPrice']);
    }

    #[Test]
    public function it_can_prepare_an_nft_transfer_transaction()
    {
        Config::set('multiversx.network', 'devnet');
        $transactionService = $this->app->make(TransactionService::class);
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class); // Instantiate WalletService here

        $sender = 'erd1qqqqqqqqqqqqqqqpqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq6gq4hu';
        // Generate a valid receiver address within the test
        $generatedReceiverWallet = $walletService->createWallet();
        $receiver = $generatedReceiverWallet->address;

        // Use strlen for consistency with the passing wallet test
        $this->assertEquals(62, strlen($receiver), "Length check inside test for receiver");
        $collection = 'MYNFT-abcdef';
        $nonce = 10; // NFT specific nonce
        $quantity = gmp_init(1); // Quantité pour NFT
        $txNonce = 25;

        // Prepare using a hypothetical new method
        $preparedTx = $transactionService->prepareNftTransfer([
            'sender' => $sender,
            'receiver' => $receiver,
            'collection' => $collection,
            'nonce' => $nonce,
            'quantity' => $quantity,
            'txNonce' => $txNonce,
            'gasLimit' => 1000000, // Gas limit might be higher for NFT transfers
        ]);

        $this->assertIsArray($preparedTx);
        $this->assertEquals(0, $preparedTx['value'], "Value should be 0 for standard NFT transfers");
        // Le receiver de la transaction est l'adresse de l'expéditeur pour un transfert NFT/SFT
        $this->assertEquals($sender, $preparedTx['receiver'], "Transaction receiver should be the sender for NFT transfers.");
        $this->assertEquals($sender, $preparedTx['sender']);
        $this->assertEquals($txNonce, $preparedTx['nonce']);
        $this->assertArrayHasKey('data', $preparedTx);

        // Expected data: ESDTNFTTransfer@collection_hex@nonce_hex@quantity_hex@receiver_hex
        $collectionHex = bin2hex($collection);
        $nonceHex = dechex($nonce);
        $quantityHex = gmp_strval($quantity, 16);
        // Calculer l'hex attendu pour le receiver en utilisant la vraie fonction
        $receiverHex = \KmcpG\MultiversxSdkLaravel\Services\WalletService::bech32ToPublicKeyHex($receiver);
        $expectedData = "ESDTNFTTransfer@{$collectionHex}@{$nonceHex}@{$quantityHex}@{$receiverHex}";

        $this->assertEquals(base64_encode($expectedData), $preparedTx['data'], "Data field format is incorrect for NFT transfer.");

        // Check standard fields
        $this->assertEquals('D', $preparedTx['chainID']);
        $this->assertEquals(1, $preparedTx['version']);
        $this->assertEquals(1000000000, $preparedTx['gasPrice']);
    }
} 