<?php

namespace KmcpG\MultiversxSdkLaravel\Services;

use Illuminate\Support\Facades\Config;
use kornrunner\Keccak; // Import Keccak
use KmcpG\MultiversxSdkLaravel\Utils\Converter; // Import Converter
use Elliptic\EC; // Import from simplito/elliptic-php

class TransactionService
{
    protected MultiversxClient $client;

    public function __construct(MultiversxClient $client)
    {
        $this->client = $client;
    }

    /**
     * Prepares the data structure for a transaction before signing.
     * (Internal helper used by other prepare* methods)
     *
     * @param array $params Must contain: sender, receiver, value, nonce, gasLimit, ?data
     * @return array Transaction structure ready for serialization/signing.
     * @throws \InvalidArgumentException
     */
    protected function prepareTransaction(array $params): array
    {
        // Validate essential parameters (can be improved)
        $required = ['sender', 'receiver', 'value', 'nonce', 'gasLimit'];
        foreach ($required as $key) {
            if (!isset($params[$key])) {
                throw new \InvalidArgumentException("Missing transaction parameter: {$key}");
            }
        }

        $network = Config::get('multiversx.network', 'mainnet');
        $chainID = match (strtolower($network)) {
            'mainnet' => '1',
            'testnet' => 'T',
            'devnet' => 'D',
            default => throw new \InvalidArgumentException("Invalid Multiversx network in configuration: {$network}"),
        };

        $data = $params['data'] ?? ''; // Use an empty string if data is not provided

        return [
            'nonce' => (int) $params['nonce'], // Ensure integer type
            'value' => (string) $params['value'], // Ensure string type
            'receiver' => (string) $params['receiver'],
            'sender' => (string) $params['sender'],
            'gasPrice' => (int) Config::get('multiversx.default_gas_price', 1000000000), // Get from config
            'gasLimit' => (int) $params['gasLimit'],
            'data' => base64_encode($data),
            'chainID' => $chainID,
            'version' => (int) Config::get('multiversx.default_tx_version', 1), // Get from config
            // Add 'options' for Guardian transactions later if needed
        ];
    }

    /**
     * Signs a prepared transaction with a private key.
     *
     * @param array $preparedTx The prepared transaction.
     * @param string $privateKeyHex The private key in hexadecimal.
     * @return array The transaction with the 'signature' field added.
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function signTransaction(array $preparedTx, string $privateKeyHex): array
    {
        // 1. Serialize the transaction to JSON (specific order, no spaces)
        // Order is crucial for signing.
        $txToSerialize = [
            'nonce' => $preparedTx['nonce'],
            'value' => $preparedTx['value'],
            'receiver' => $preparedTx['receiver'],
            'sender' => $preparedTx['sender'],
            'gasPrice' => $preparedTx['gasPrice'],
            'gasLimit' => $preparedTx['gasLimit'],
            'data' => $preparedTx['data'] ?? null, // Can be null or base64 encoded
            'chainID' => $preparedTx['chainID'],
            'version' => $preparedTx['version'],
            // Add 'options' if present and relevant
            // 'options' => $preparedTx['options'] ?? null,
        ];
        // Remove null keys to exactly match what the API expects
        $txToSerialize = array_filter($txToSerialize, fn ($value) => $value !== null);

        $jsonPayload = json_encode($txToSerialize, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($jsonPayload === false) {
            throw new \RuntimeException("Failed to JSON encode transaction.");
        }

        // 2. Calculate Keccak256 hash (binary result)
        $messageHashBinary = Keccak::hash($jsonPayload, 256);

        // 3. Sign with simplito/elliptic-php
        if (!ctype_xdigit($privateKeyHex) || strlen($privateKeyHex) !== 64) {
            throw new \InvalidArgumentException("Private key must be a 64-character hexadecimal string.");
        }
        
        try {
            $ec = new EC('secp256k1');
            // Load private key
            $key = $ec->keyFromPrivate($privateKeyHex, 'hex');

            // Sign the hash (note: simplito expects the hash as hex)
            $messageHashHex = bin2hex($messageHashBinary);
            $signature = $key->sign($messageHashHex, ['canonical' => true]); // Use canonical signature

            // 4. Concatenate r and s in hexadecimal (64 bytes = 128 hex chars)
            $rHex = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
            $sHex = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
            $txSignatureHex = $rHex . $sHex;

            // Add the signature to the transaction
            $preparedTx['signature'] = $txSignatureHex;

            return $preparedTx;
        } catch (\Exception $e) {
            // Catch potential errors during key loading or signing
            throw new \RuntimeException("Transaction signing failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Sends a signed transaction to the Multiversx API.
     *
     * @param array $signedTx The signed transaction (with the 'signature' field).
     * @return array The API response (usually containing the txHash).
     * @throws \KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException On API call failure.
     */
    public function sendTransaction(array $signedTx): array
    {
        // Validate signature presence?
        if (!isset($signedTx['signature']) || !ctype_xdigit($signedTx['signature']) || strlen($signedTx['signature']) !== 128) {
            throw new \InvalidArgumentException("Transaction not signed or invalid signature provided.");
        }

        // Use the client to send the POST request
        // The client already handles raising MultiversxException on failure
        return $this->client->post('/transactions', $signedTx);
    }

    /**
     * Retrieves the details and status of a transaction from the API.
     *
     * @param string $txHash The transaction hash.
     * @return array Transaction details and status.
     * @throws \InvalidArgumentException If the hash format is invalid.
     * @throws \KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException On API call failure.
     */
    public function getTransactionStatus(string $txHash): array
    {
        // Basic validation for the hash
        if (!ctype_xdigit($txHash) || strlen($txHash) !== 64) {
            throw new \InvalidArgumentException("Invalid transaction hash format provided.");
        }

        // Use the client for the GET request
        return $this->client->get("/transactions/{$txHash}");
    }

    /**
     * Prepares an ESDT transfer transaction structure.
     *
     * @param array $params Must contain: sender, receiver, tokenIdentifier, amount (GMP object), nonce, gasLimit
     * @return array Transaction structure ready for signing.
     * @throws \InvalidArgumentException
     */
    public function prepareEsdtTransfer(array $params): array
    {
        $required = ['sender', 'receiver', 'tokenIdentifier', 'amount', 'nonce', 'gasLimit'];
        foreach ($required as $key) {
            if (!isset($params[$key])) {
                throw new \InvalidArgumentException("Missing parameter for ESDT transfer: {$key}");
            }
        }
        if (!($params['amount'] instanceof \GMP)) {
            throw new \InvalidArgumentException("Amount must be a GMP object for ESDT transfer.");
        }
        if (empty($params['tokenIdentifier'])) {
            throw new \InvalidArgumentException('Token identifier cannot be empty.');
        }

        // Convert amount to hex (minimal padding for 0)
        $amountHex = gmp_strval($params['amount'], 16);
        $amountHex = ($amountHex === '') ? '00' : $amountHex; // Use 00 for 0, otherwise raw hex

        // Construct the data field: ESDTTransfer@token_hex@amount_hex
        $tokenHex = bin2hex($params['tokenIdentifier']);

        $data = "ESDTTransfer@{$tokenHex}@{$amountHex}";

        // Prepare the base transaction structure, forcing value to 0
        $baseParams = [
            'sender' => $params['sender'],
            'receiver' => $params['receiver'],
            'value' => '0', // Value must be 0 for standard ESDT transfers
            'nonce' => $params['nonce'],
            'gasLimit' => $params['gasLimit'],
            'data' => $data,
        ];

        return $this->prepareTransaction($baseParams);
    }

    /**
     * Prepares a smart contract call transaction structure.
     *
     * @param array $params Must contain: sender, contractAddress, functionName, arguments (array), value, nonce, gasLimit
     * @return array Transaction structure ready for signing.
     * @throws \InvalidArgumentException
     */
    public function prepareSmartContractCall(array $params): array
    {
        $required = ['sender', 'contractAddress', 'functionName', 'arguments', 'value', 'nonce', 'gasLimit'];
        foreach ($required as $key) {
            if (!isset($params[$key])) {
                throw new \InvalidArgumentException("Missing parameter for smart contract call: {$key}");
            }
        }
        if (!is_array($params['arguments'])) {
            throw new \InvalidArgumentException("'arguments' must be an array.");
        }
        if (empty($params['functionName'])) {
            throw new \InvalidArgumentException('Function name cannot be empty.');
        }
        // Basic validation for contract address
        if (!str_starts_with($params['contractAddress'], 'erd1') || strlen($params['contractAddress']) !== 62) {
             throw new \InvalidArgumentException('Invalid contract address format.');
        }

        // Encode arguments using Converter
        $encodedArgs = Converter::encodeSmartContractArgs($params['arguments']);
        // Build data field using Converter
        $data = Converter::buildContractDataField($params['functionName'], $encodedArgs);

        // Prepare the base transaction structure
        $baseParams = [
            'sender' => $params['sender'],
            'receiver' => $params['contractAddress'], // Receiver is the contract
            'value' => $params['value'], // Value sent to the contract
            'nonce' => $params['nonce'],
            'gasLimit' => $params['gasLimit'],
            'data' => $data,
        ];

        return $this->prepareTransaction($baseParams);
    }

    /**
     * Prepares an NFT/SFT transfer transaction structure.
     *
     * @param array $params Must contain: sender, receiver (actual recipient), collection, nonce (NFT/SFT nonce), quantity (GMP), txNonce, gasLimit
     * @return array Transaction structure ready for signing.
     * @throws \InvalidArgumentException
     */
    public function prepareNftTransfer(array $params): array
    {
        $required = ['sender', 'receiver', 'collection', 'nonce', 'quantity', 'txNonce', 'gasLimit'];
        foreach ($required as $key) {
            if (!isset($params[$key])) {
                throw new \InvalidArgumentException("Missing parameter for NFT/SFT transfer: {$key}");
            }
        }
        if (!($params['quantity'] instanceof \GMP)) {
            throw new \InvalidArgumentException("Quantity must be a GMP object for NFT/SFT transfer.");
        }
        if (!is_int($params['nonce']) || $params['nonce'] < 0) {
             throw new \InvalidArgumentException("Nonce must be a non-negative integer for NFT/SFT.");
        }
        if (empty($params['collection'])) {
            throw new \InvalidArgumentException('Collection identifier cannot be empty.');
        }

        // Use Converter for hex amount
        $quantityHex = gmp_strval($params['quantity'], 16);
        $quantityHex = (strlen($quantityHex) % 2 !== 0) ? '0' . $quantityHex : $quantityHex;
        $quantityHex = ($quantityHex === '') ? '00' : $quantityHex; // Ensure 0 is represented as 00

        // Construct the data field: ESDTNFTTransfer@collection_hex@nonce_hex@quantity_hex@receiver_hex
        $collectionHex = bin2hex($params['collection']);
        // Nonce for NFT is already an int, convert to raw hex
        $nonceHex = dechex($params['nonce']);
        // Quantity is GMP, convert to raw hex
        $quantityHex = gmp_strval($params['quantity'], 16);

        // Convert receiver address to hex using the utility method
        $receiverHex = \KmcpG\MultiversxSdkLaravel\Services\WalletService::bech32ToPublicKeyHex($params['receiver']);

        $data = "ESDTNFTTransfer@{$collectionHex}@{$nonceHex}@{$quantityHex}@{$receiverHex}";

        // Prepare the base transaction structure
        $baseParams = [
            'sender' => $params['sender'],
            // IMPORTANT: For NFT/SFT transfers, the transaction receiver is the sender!
            'receiver' => $params['sender'],
            'value' => '0', // Value is 0
            'nonce' => $params['txNonce'], // Use the transaction nonce here
            'gasLimit' => $params['gasLimit'],
            'data' => $data,
        ];

        return $this->prepareTransaction($baseParams);
    }
} 