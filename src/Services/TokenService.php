<?php

namespace KmcpG\MultiversxSdkLaravel\Services;

class TokenService
{
    protected MultiversxClient $client;

    public function __construct(MultiversxClient $client)
    {
        $this->client = $client;
    }

    /**
     * Retrieves properties for a given token identifier (ESDT, SFT, NFT).
     *
     * @param string $identifier The token identifier (e.g., 'WEGLD-bd4d79').
     * @return array The token properties.
     * @throws \KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException On API call failure.
     */
    public function getTokenProperties(string $identifier): array
    {
        // Basic validation (can be improved, e.g., regex check)
        if (empty($identifier)) {
            throw new \InvalidArgumentException('Token identifier cannot be empty.');
        }

        // Use the client to perform the GET request
        return $this->client->get("/tokens/{$identifier}");
    }

    /**
     * Retrieves token details (including balance) for a specific address and token identifier.
     *
     * @param string $address The bech32 address.
     * @param string $identifier The token identifier.
     * @return array Token details for the account.
     * @throws \InvalidArgumentException If address or identifier are invalid.
     * @throws \KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException On API call failure.
     */
    public function getTokenBalance(string $address, string $identifier): array
    {
        // Basic validation
        if (empty($identifier)) {
            throw new \InvalidArgumentException('Token identifier cannot be empty.');
        }
        // Reuse address validation from WalletService? Requires dependency injection.
        // For now, basic check:
        if (!str_starts_with($address, 'erd1') || strlen($address) !== 62) {
             throw new \InvalidArgumentException('Invalid address format.');
        }

        // Use the client for the GET request
        $endpoint = "/accounts/{$address}/tokens/{$identifier}";
        return $this->client->get($endpoint);
    }

    /**
     * Lists all tokens held by a specific address.
     *
     * @param string $address The bech32 address.
     * @param int|null $from Optional pagination: start index.
     * @param int|null $size Optional pagination: number of items per page.
     * @return array A list of token details/balances for the account.
     * @throws \InvalidArgumentException If the address format is invalid.
     * @throws \KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException On API call failure.
     */
    public function listAccountTokens(string $address, ?int $from = null, ?int $size = null): array
    {
        // Basic validation
        if (!str_starts_with($address, 'erd1') || strlen($address) !== 62) {
             throw new \InvalidArgumentException('Invalid address format.');
        }

        // Build query parameters if provided
        $queryParams = [];
        if (!is_null($from)) {
            $queryParams['from'] = $from;
        }
        if (!is_null($size)) {
            $queryParams['size'] = $size;
        }

        // Use the client for the GET request, passing query parameters
        return $this->client->get("/accounts/{$address}/tokens", $queryParams);
    }

    // TODO: Add methods for interacting with tokens (ESDT, SFT, NFT)
} 