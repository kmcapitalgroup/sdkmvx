<?php

namespace KmcpG\MultiversxSdkLaravel\Tests\Integration;

use KmcpG\MultiversxSdkLaravel\Facades\Multiversx;
use KmcpG\MultiversxSdkLaravel\Services\TransactionService;
use KmcpG\MultiversxSdkLaravel\Services\WalletService;
use KmcpG\MultiversxSdkLaravel\Tests\IntegrationTestCase; // Base class for integration tests
use KmcpG\MultiversxSdkLaravel\Utils\Converter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class TransactionIntegrationTest extends IntegrationTestCase
{
    protected string $senderPrivateKey;
    protected string $senderAddress;
    protected string $receiverAddress;

    protected function setUp(): void
    {
        parent::setUp();

        // Load credentials from environment variables - DO NOT COMMIT KEYS!
        $pk = env('MULTIVERSX_TEST_SENDER_PK');
        $sender = env('MULTIVERSX_TEST_SENDER_ADDRESS');
        $receiver = env('MULTIVERSX_TEST_RECEIVER_ADDRESS');

        // Check if variables are set BEFORE assigning to typed properties
        if (!$pk || !$sender || !$receiver) {
            $this->markTestSkipped(
                'Integration test environment variables not set (MULTIVERSX_TEST_SENDER_PK, MULTIVERSX_TEST_SENDER_ADDRESS, MULTIVERSX_TEST_RECEIVER_ADDRESS).'
            );
        }

        // Assign only if variables were found
        $this->senderPrivateKey = $pk;
        $this->senderAddress = $sender;
        $this->receiverAddress = $receiver;
    }

    #[Test]
    public function it_can_send_a_real_egld_transaction_on_devnet()
    {
        /** @var TransactionService $txService */
        $txService = $this->app->make(TransactionService::class);
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);

        // Ensure we are configured for devnet for this test
        // Could be set in phpunit.xml or .env.testing
        $this->assertEquals('D', config('multiversx.chain_id'), 'Test requires Devnet configuration.');

        // 1. Get current nonce
        try {
            $account = $walletService->getAccount($this->senderAddress);
            $nonce = $account['nonce'];
        } catch (\Exception $e) {
            $this->fail("Failed to get account details for sender: " . $e->getMessage());
        }

        // 2. Prepare transaction
        $amountToSend = '0.0001'; // Small amount for testing
        $params = [
            'sender' => $this->senderAddress,
            'contractAddress' => $this->receiverAddress, 
            'functionName' => '',
            'arguments' => [],
            'value' => Converter::egldToAtomic($amountToSend),
            'nonce' => $nonce,
            'gasLimit' => 50000,
        ];

        try {
            $preparedTx = $txService->prepareSmartContractCall($params);
            $signedTx = $txService->signTransaction($preparedTx, $this->senderPrivateKey);
        } catch (\Exception $e) {
            $this->fail("Failed to prepare or sign transaction: " . $e->getMessage());
        }

        // 3. Send transaction
        try {
            $result = $txService->sendTransaction($signedTx);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('txHash', $result);
            $this->assertNotEmpty($result['txHash']);
            $txHash = $result['txHash'];
            echo "\nTransaction sent to Devnet: {$txHash}\n";
            echo "Waiting a bit for transaction to be processed...\n";
            sleep(10); // Wait for the transaction to likely be processed (adjust as needed)

        } catch (\Exception $e) {
            $this->fail("Failed to send transaction: " . $e->getMessage());
        }
        
        // 4. (Optional but recommended) Verify transaction status
        try {
            $status = $txService->getTransactionStatus($txHash);
            $this->assertIsArray($status);
            $this->assertEquals('success', $status['status'], "Transaction status was not successful.");
             echo "Transaction status confirmed: " . $status['status'] . "\n";

        } catch (\Exception $e) {
            $this->fail("Failed to get transaction status: " . $e->getMessage());
        }
    }
} 