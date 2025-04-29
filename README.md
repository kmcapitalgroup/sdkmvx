# MultiversX SDK for Laravel

Laravel SDK for interacting with the MultiversX blockchain.

## Installation

```bash
composer require kmcpg/multiversx-sdk-laravel
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=multiversx-config
```

Set your environment variables in `.env`:

```dotenv
MULTIVERSX_NETWORK=devnet
MULTIVERSX_API_URL=https://devnet-api.multiversx.com
```

## Usage

```php
use KmcpG\MultiversxSdkLaravel\Facades\Multiversx;

// Get account details
$account = Multiversx::getAccount('erd1...');

// Create a new wallet (address generation needs proper implementation)
$wallet = Multiversx::createWallet();

// Send a transaction (needs implementation)
// $result = Multiversx::sendTransaction([...]);

// Use other services via dependency injection
use KmcpG\MultiversxSdkLaravel\Services\TransactionService;

public function __construct(private TransactionService $transactionService) {}

// ...
```

## Testing

```bash
composer test
``` 