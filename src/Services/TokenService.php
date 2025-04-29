<?php

namespace KmcpG\MultiversxSdkLaravel\Services;

class TokenService
{
    protected MultiversxClient $client;

    public function __construct(MultiversxClient $client)
    {
        $this->client = $client;
    }

    // TODO: Ajouter méthodes pour interagir avec les tokens (ESDT, SFT, NFT)
} 