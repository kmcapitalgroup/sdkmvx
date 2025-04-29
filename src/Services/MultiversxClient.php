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

    public function get(string $endpoint): array
    {
        $response = Http::get($this->baseUrl . $endpoint);
        if (!$response->successful()) {
            throw new MultiversxException("API GET failed: " . $response->body());
        }
        return $response->json();
    }

    public function post(string $endpoint, array $data): array
    {
        $response = Http::post($this->baseUrl . $endpoint, $data);
        if (!$response->successful()) {
            throw new MultiversxException("API POST failed: " . $response->body());
        }
        return $response->json();
    }
} 