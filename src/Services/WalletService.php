<?php

namespace KmcpG\MultiversxSdkLaravel\Services;

use KmcpG\MultiversxSdkLaravel\Contracts\MultiversxInterface;
use BitWasp\Bech32;
use Elliptic\EC; // Import from simplito/elliptic-php
use BN\BN; // Import BN from simplito/bn-php

class WalletService implements MultiversxInterface
{
    protected MultiversxClient $client;
    private const HRP = 'erd'; // Human-Readable Part for Multiversx

    public function __construct(MultiversxClient $client)
    {
        $this->client = $client;
    }

    /**
     * Generates a new wallet (private key, public key, Multiversx Bech32 address)
     * using simplito/elliptic-php.
     */
    public function createWallet(): object
    {
        // 1. Initialize the elliptic curve context for secp256k1
        $ec = new EC('secp256k1');

        // 2. Generate a new key pair
        $keyPair = $ec->genKeyPair();

        // 3. Get the private key as a hex string (padded to 64 chars)
        $privateKeyHex = str_pad($keyPair->getPrivate('hex'), 64, '0', STR_PAD_LEFT);

        // 4. Get the public key point coordinates (X and Y) as BN objects
        $pubPoint = $keyPair->getPublic();
        $pubX = $pubPoint->getX();
        $pubY = $pubPoint->getY();

        // 5. Convert coordinates to hex and pad to 32 bytes (64 hex chars) each
        $pubXHex = str_pad($pubX->toString(16), 64, '0', STR_PAD_LEFT);
        $pubYHex = str_pad($pubY->toString(16), 64, '0', STR_PAD_LEFT);

        // 6. The public key hex for the address is the X coordinate
        // MultiversX addresses use only the X coordinate (32 bytes) of the public key.
        $publicKeyHexForAddress = $pubXHex;

        // 7. Convert the public key hex for address to binary for Bech32 encoding
        $publicKeyBinaryForAddress = hex2bin($publicKeyHexForAddress);

        // 8. Bech32 Encoding Logic (using BitWasp library)
        $data = array_values(unpack('C*', $publicKeyBinaryForAddress));
        $convertedData = Bech32\convertBits($data, count($data), 8, 5, true);
        if ($convertedData === false) {
            throw new \RuntimeException("Failed to convert public key bits for Bech32 encoding.");
        }
        $address = Bech32\encode(self::HRP, $convertedData);
        // --- End Bech32 Logic ---

        return (object)[
            'privateKey' => $privateKeyHex,
            // Return the 32-byte public key hex used for the address
            'publicKey' => $publicKeyHexForAddress,
            'address' => $address,
        ];
    }

    public function sendTransaction(array $params): array
    {
        // TODO: Implement signing and sending via client
        // Signing should now ideally use TransactionService::signTransaction
        // which uses mdanter/ecc currently.
        throw new \BadMethodCallException("sendTransaction directly via WalletService is not implemented.");
        // return [];
    }

    public function getAccount(string $address): array
    {
        // Before making the call, validate the address format
        if (! $this->isValidAddress($address)) {
            throw new \InvalidArgumentException("Invalid address format: {$address}");
        }
        return $this->client->get("/accounts/{$address}");
    }

    /**
     * Validates the format of a Multiversx (Bech32) address.
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
            // Attempt to decode to verify checksum
            Bech32\decode($address);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Converts a valid Bech32 address (erd1...) to its corresponding public key hex string (32 bytes / 64 chars).
     *
     * @param string $address The Bech32 address.
     * @return string The public key hex string.
     * @throws \InvalidArgumentException If the address is invalid or conversion fails.
     */
    public static function bech32ToPublicKeyHex(string $address): string
    {
        // Reuse validation logic (or call isValidAddress if not static)
        // Use mb_strlen just in case and provide more debug info in exception
        $startsWithErd1 = str_starts_with($address, self::HRP . '1');
        $length = mb_strlen($address, '8bit');

        if (!$startsWithErd1 || $length !== 62) {
            throw new \InvalidArgumentException(
                "Invalid address format for conversion. Starts with erd1: "
                . ($startsWithErd1 ? 'Yes' : 'No')
                . ", Expected Length: 62, Actual Length: " . $length
            );
        }

        try {
            list($hrp, $dataParts) = Bech32\decode($address);

            if ($hrp !== self::HRP) {
                throw new \InvalidArgumentException("Invalid address HRP (human-readable part).");
            }

            // Convert back from 5-bit data parts to 8-bit bytes
            $bytes = Bech32\convertBits($dataParts, count($dataParts), 5, 8, false);
            if ($bytes === false) {
                throw new \RuntimeException("Failed to convert address bits.");
            }

            // Ensure we have 32 bytes
            if (count($bytes) !== 32) {
                throw new \InvalidArgumentException("Decoded address does not represent a 32-byte public key.");
            }

            // Pack bytes into a binary string and convert to hex
            $binaryString = pack('C*', ...$bytes);
            return bin2hex($binaryString);

        } catch (Bech32\Exception\Bech32Exception | Bech32\Exception\InvalidChecksumException $e) {
            throw new \InvalidArgumentException("Invalid Bech32 address checksum or format: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            // Catch other potential errors during conversion
            throw new \RuntimeException("Address conversion failed: " . $e->getMessage(), 0, $e);
        }
    }
} 