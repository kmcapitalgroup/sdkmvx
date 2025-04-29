<?php

namespace KmcpG\MultiversxSdkLaravel\Services;

use Illuminate\Support\Facades\Http;
use KmcpG\MultiversxSdkLaravel\Exceptions\MultiversxException;

class MultiversxClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('multiversx.api_url');
    }

    /**
     * Performs a GET request to the Multiversx API.
     *
     * @param string $endpoint The API endpoint (e.g., '/accounts/...').
     * @param array $queryParams Optional query parameters as key-value pairs.
     * @return array The decoded JSON response.
     * @throws MultiversxException On API call failure.
     */
    public function get(string $endpoint, array $queryParams = []): array
    {
        $request = Http::baseUrl($this->baseUrl); // Set base URL for the request

        // Add query parameters if provided
        if (!empty($queryParams)) {
            $request->withQueryParameters($queryParams);
        }

        $response = $request->get($endpoint);

        if (!$response->successful()) {
            // Include endpoint and potentially status code/body in error
            $errorMessage = sprintf(
                "API GET request to %s failed with status %d: %s",
                $endpoint,
                $response->status(),
                $response->body()
            );
            throw new MultiversxException($errorMessage);
        }

        return $response->json();
    }

    /**
     * Performs a POST request to the Multiversx API.
     *
     * @param string $endpoint The API endpoint (e.g., '/transactions').
     * @param array $data The data payload for the POST request.
     * @return array The decoded JSON response.
     * @throws MultiversxException On API call failure.
     */
    public function post(string $endpoint, array $data): array
    {
        $response = Http::baseUrl($this->baseUrl)->post($endpoint, $data);

        if (!$response->successful()) {
             // Include endpoint and potentially status code/body in error
            $errorMessage = sprintf(
                "API POST request to %s failed with status %d: %s",
                $endpoint,
                $response->status(),
                $response->body()
            );
            throw new MultiversxException($errorMessage);
        }
        return $response->json();
    }
} 