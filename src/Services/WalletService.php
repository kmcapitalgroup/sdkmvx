<?php

namespace KmcpG\MultiversxSdkLaravel\Services;

use KmcpG\MultiversxSdkLaravel\Contracts\MultiversxInterface;
use BitWasp\Bech32;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Serializer\PrivateKey\PemPrivateKeySerializer;
use Mdanter\Ecc\Serializer\PrivateKey\DerPrivateKeySerializer;
use Mdanter\Ecc\Serializer\PublicKey\PemPublicKeySerializer;
use Mdanter\Ecc\Serializer\PublicKey\DerPublicKeySerializer;
use Mdanter\Ecc\Serializer\Point\UncompressedPointSerializer; // Pour obtenir les bytes de la clé publique

class WalletService implements MultiversxInterface
{
    protected MultiversxClient $client;
    private const HRP = 'erd';

    public function __construct(MultiversxClient $client)
    {
        $this->client = $client;
    }

    /**
     * Génère un nouveau wallet (clé privée, publique, adresse Bech32 Multiversx)
     * en utilisant mdanter/ecc.
     */
    public function createWallet(): object
    {
        $adapter = EccFactory::getAdapter();
        $generator = EccFactory::getSecgCurves()->generator256k1(); // Utiliser generator256k1()

        // Générer la clé privée
        $privateKey = $generator->createPrivateKey();

        // Obtenir la clé publique associée
        $publicKey = $privateKey->getPublicKey();

        // Sérialiser la clé publique au format non compressé (bytes bruts)
        $pointSerializer = new UncompressedPointSerializer($adapter);
        $publicKeyBinaryString = $pointSerializer->serialize($publicKey->getPoint());

        // Pour l'adresse Multiversx, nous utilisons les 32 octets de la clé publique
        // (correspondant aux 32 octets après le préfixe 0x04).
        $publicKeyBinaryForAddress = substr($publicKeyBinaryString, 1, 32);

        // Convertir ces 32 octets en hexadécimal pour l'affichage/stockage
        $publicKeyHex = bin2hex($publicKeyBinaryForAddress);

        // Convertir la clé privée en hexadécimal (pour l'affichage/stockage si besoin)
        $privateKeyHex = gmp_strval($privateKey->getSecret(), 16);

        // --- Logique Bech32 (inchangée, utilise $publicKeyBinaryForAddress) ---
        $data = array_values(unpack('C*', $publicKeyBinaryForAddress));
        $convertedData = Bech32\convertBits($data, count($data), 8, 5, true);
        $address = Bech32\encode(self::HRP, $convertedData);
        // --- Fin Logique Bech32 ---

        return (object)[
            'privateKey' => $privateKeyHex,
            'publicKey' => $publicKeyHex,
            'address' => $address,
        ];
    }

    public function sendTransaction(array $params): array
    {
        // TODO: implémenter signature et envoi via client
        // La signature utilisera aussi mdanter/ecc avec la $privateKey
        return [];
    }

    public function getAccount(string $address): array
    {
        if (! $this->isValidAddress($address)) {
            throw new \InvalidArgumentException("Format d'adresse invalide: {$address}");
        }
        return $this->client->get("/accounts/{$address}");
    }

    /**
     * Valide le format d'une adresse Multiversx (Bech32).
     *
     * @param string $address
     * @return bool
     */
    public function isValidAddress(string $address): bool
    {
        if (strlen($address) !== 62 || !str_starts_with($address, self::HRP . '1')) {
            return false;
        }

        try {
            Bech32\decode($address);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
} 