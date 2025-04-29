<?php

namespace KmcpG\MultiversxSdkLaravel\Contracts;

interface MultiversxInterface
{
    public function createWallet(): object;
    public function sendTransaction(array $params): array;
    public function getAccount(string $address): array;
    // Ajouter autres méthodes selon besoins
} 