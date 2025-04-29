<?php

namespace KmcpG\MultiversxSdkLaravel\Services;

class TransactionService
{
    protected MultiversxClient $client;

    public function __construct(MultiversxClient $client)
    {
        $this->client = $client;
    }

    // TODO: Ajouter méthodes pour construire, signer, envoyer des transactions
} 