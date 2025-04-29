# MultiversX SDK for Laravel

A Laravel SDK for interacting with the MultiversX blockchain (formerly Elrond).

## Installation

```bash
composer require kmcpg/multiversx-sdk-laravel
```

The service provider and facade are auto-discovered.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=multiversx-config
```

This will create a `config/multiversx.php` file. You can customize the network, API URL, and default transaction parameters:

```php
// config/multiversx.php
return [
    // 'mainnet', 'testnet', or 'devnet'
    'network' => env('MULTIVERSX_NETWORK', 'devnet'),

    // Public API URL
    'api_url' => env('MULTIVERSX_API_URL', 'https://devnet-api.multiversx.com'),

    // Default gas price for transactions
    'default_gas_price' => env('MULTIVERSX_DEFAULT_GAS_PRICE', 1000000000),

    // Default transaction version
    'default_tx_version' => env('MULTIVERSX_DEFAULT_TX_VERSION', 1),
];
```

Set your environment variables in `.env` if needed:

```dotenv
MULTIVERSX_NETWORK=devnet
MULTIVERSX_API_URL=https://devnet-api.multiversx.com
# MULTIVERSX_DEFAULT_GAS_PRICE=1000000000
# MULTIVERSX_DEFAULT_TX_VERSION=1
```

## Usage

You can use the `Multiversx` facade or inject the specific services you need.

### Utility Class (`Converter`)

The SDK provides a `KmcpG\MultiversxSdkLaravel\Utils\Converter` class with static methods for common tasks:

```php
use KmcpG\MultiversxSdkLaravel\Utils\Converter;

// Convert EGLD to atomic units (string)
$atomicValue = Converter::egldToAtomic('1.5'); // "1500000000000000000"

// Convert ESDT amount to atomic units (string)
// Requires knowing the token decimals (fetch via TokenService)
$atomicEsdt = Converter::esdtToAtomic('123.45', 6); // Assuming 6 decimals: "123450000"

// Encode arguments for a Smart Contract call (array of hex strings)
$rawArgs = [
    gmp_init(1000),
    'erd1...', // Bech32 address
    true,
    'hello'
];
$encodedArgs = Converter::encodeSmartContractArgs($rawArgs);
// Result: ['03e8', 'publicKeyHex...', '01', '68656c6c6f']

// Build the data field for a Smart Contract call (string)
$dataField = Converter::buildContractDataField('myFunction', $encodedArgs);
// Result: "myFunction@03e8@publicKeyHex...@01@68656c6c6f"
```

### Wallet Operations (via Facade or `WalletService`)

```php
use KmcpG\MultiversxSdkLaravel\Facades\Multiversx;
// or inject KmcpG\MultiversxSdkLaravel\Services\WalletService

// Get account details (Nonce, Balance, etc.)
$account = Multiversx::getAccount('erd1...');
// $nonce = $account['nonce'];
// $balance = $account['balance'];

// Create a new wallet (generates private/public keys and address)
$wallet = Multiversx::createWallet();
// $privateKey = $wallet->privateKey;
// $publicKey = $wallet->publicKey;
// $address = $wallet->address;

// Validate an address
$isValid = Multiversx::isValidAddress('erd1...'); // Returns true or false

// Convert Bech32 address to Public Key Hex (Static Utility)
use KmcpG\MultiversxSdkLaravel\Services\WalletService;
$publicKeyHex = WalletService::bech32ToPublicKeyHex('erd1...');
```

### Transaction Operations (via `TransactionService`)

Inject `KmcpG\MultiversxSdkLaravel\Services\TransactionService`.

```php
use KmcpG\MultiversxSdkLaravel\Services\TransactionService;
use KmcpG\MultiversxSdkLaravel\Services\WalletService;
use KmcpG\MultiversxSdkLaravel\Utils\Converter; // Import Converter

class MyController
{
    protected TransactionService $txService;
    protected WalletService $walletService;

    public function __construct(TransactionService $txService, WalletService $walletService)
    {
        $this->txService = $txService;
        $this->walletService = $walletService; // Typically, you'd retrieve the private key securely
    }

    // Helper to get address (implementation specific)
    private function getAddressFromPrivateKey(string $privateKeyHex): string
    {
        // You might implement this using WalletService or keep it separate
        // Requires mdanter/ecc currently
        $adapter = \Mdanter\Ecc\EccFactory::getAdapter();
        $generator = \Mdanter\Ecc\EccFactory::getSecgCurves()->generator256k1();
        $key = $generator->getPrivateKeyFrom(gmp_init($privateKeyHex, 16));
        $point = $key->getPublicKey()->getPoint();
        $pkBin = substr((new \Mdanter\Ecc\Serializer\Point\UncompressedPointSerializer($adapter))->serialize($point), 1, 32);
        $data = array_values(unpack('C*', $pkBin));
        $convertedData = \BitWasp\Bech32\convertBits($data, count($data), 8, 5, true);
        return \BitWasp\Bech32\encode('erd', $convertedData);
    }

    public function sendEgldExample(string $senderPrivateKeyHex, string $receiverAddress, string $amountEgld, int $nonce)
    {
        // 1. Prepare basic EGLD transfer
        $params = [
            'sender' => $this->getAddressFromPrivateKey($senderPrivateKeyHex),
            'contractAddress' => $receiverAddress,
            'functionName' => '', // Empty function name for EGLD transfer
            'arguments' => [], // No arguments for EGLD transfer
            'value' => Converter::egldToAtomic($amountEgld), // Use Converter
            'nonce' => $nonce,
            'gasLimit' => 50000,
        ];
        // Note: Sending EGLD uses prepareSmartContractCall structure
        $preparedTx = $this->txService->prepareSmartContractCall($params);

        // 2. Sign
        $signedTx = $this->txService->signTransaction($preparedTx, $senderPrivateKeyHex);

        // 3. Send
        $result = $this->txService->sendTransaction($signedTx);
        return $result;
    }

    public function sendEsdtExample(string $senderPrivateKeyHex, string $receiverAddress, string $token, string $amount, int $decimals, int $nonce)
    {
        // 1. Prepare ESDT transfer
        $params = [
            'sender' => $this->getAddressFromPrivateKey($senderPrivateKeyHex),
            'receiver' => $receiverAddress,
            'tokenIdentifier' => $token,
            // Use GMP and Converter::esdtToAtomic or just pass GMP amount
            'amount' => gmp_init(Converter::esdtToAtomic($amount, $decimals)), 
            'nonce' => $nonce,
            'gasLimit' => 600000,
        ];
        $preparedTx = $this->txService->prepareEsdtTransfer($params);

        // 2. Sign & 3. Send (same as above)
        $signedTx = $this->txService->signTransaction($preparedTx, $senderPrivateKeyHex);
        $result = $this->txService->sendTransaction($signedTx);
        return $result;
    }

     public function sendNftExample(string $senderPrivateKeyHex, string $receiverAddress, string $collection, int $nftNonce, int $txNonce)
    {
        // 1. Prepare NFT transfer
        $params = [
            'sender' => $this->getAddressFromPrivateKey($senderPrivateKeyHex),
            'receiver' => $receiverAddress,
            'collection' => $collection,
            'nonce' => $nftNonce, // The nonce of the specific NFT
            'quantity' => gmp_init(1), // Quantity is 1 for NFTs
            'txNonce' => $txNonce,
            'gasLimit' => 1000000,
        ];
        $preparedTx = $this->txService->prepareNftTransfer($params);

        // 2. Sign & 3. Send (same as above)
        $signedTx = $this->txService->signTransaction($preparedTx, $senderPrivateKeyHex);
        $result = $this->txService->sendTransaction($signedTx);
        return $result;
    }

    public function callContractExample(string $senderPrivateKeyHex, string $contractAddress, string $function, array $rawArgs, string $valueEgld, int $nonce)
    {
        // 1. Prepare SC call
        $params = [
            'sender' => $this->getAddressFromPrivateKey($senderPrivateKeyHex),
            'contractAddress' => $contractAddress,
            'functionName' => $function,
            'arguments' => $rawArgs, // Pass raw arguments here
            'value' => Converter::egldToAtomic($valueEgld), // Use Converter
            'nonce' => $nonce,
            'gasLimit' => 5000000,
        ];
        // prepareSmartContractCall now handles encoding via Converter internally
        $preparedTx = $this->txService->prepareSmartContractCall($params);

        // 2. Sign & 3. Send (same as above)
        $signedTx = $this->txService->signTransaction($preparedTx, $senderPrivateKeyHex);
        $result = $this->txService->sendTransaction($signedTx);
        return $result;
    }

    public function checkStatusExample(string $txHash)
    {
        $status = $this->txService->getTransactionStatus($txHash);
        // e.g., $status['status'] can be 'pending', 'success', 'fail'
        return $status;
    }

}
```

### Token Operations (via `TokenService`)

Inject `KmcpG\MultiversxSdkLaravel\Services\TokenService`.

```php
use KmcpG\MultiversxSdkLaravel\Services\TokenService;

// Get properties of a token (ESDT/NFT/SFT)
$properties = $tokenService->getTokenProperties('WEGLD-bd4d79');
// $name = $properties['name'];
// $decimals = $properties['decimals'];

// Get the balance of a specific token for an address
$balanceInfo = $tokenService->getTokenBalance('erd1...', 'WEGLD-bd4d79');
// $balance = $balanceInfo['balance'];

// List all tokens held by an address (supports pagination)
$allTokensPage1 = $tokenService->listAccountTokens('erd1...', 0, 25); // Get first 25 tokens
$allTokensPage2 = $tokenService->listAccountTokens('erd1...', 25, 25); // Get next 25 tokens
```

## Testing

```bash
composer test
``` 