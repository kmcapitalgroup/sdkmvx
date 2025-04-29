<?php

namespace KmcpG\MultiversxSdkLaravel\Utils;

use InvalidArgumentException;
use KmcpG\MultiversxSdkLaravel\Services\WalletService; // For bech32 utils

class Converter
{
    public const EGLD_DECIMALS = 18;

    /**
     * Convert EGLD amount to its atomic unit representation (Wei-like, 10^18).
     *
     * @param string|float|int $amountEgld Amount in EGLD.
     * @return string Amount in atomic units as a string.
     */
    public static function egldToAtomic(string|float|int $amountEgld): string
    {
        if (!is_numeric($amountEgld) || $amountEgld < 0) {
            throw new InvalidArgumentException("Invalid EGLD amount provided.");
        }

        // Use BCMath for arbitrary precision arithmetic
        $scale = self::EGLD_DECIMALS;
        $multiplier = bcpow('10', (string)$scale, $scale);
        $atomicAmount = bcmul((string)$amountEgld, $multiplier, $scale);

        // Remove trailing decimal points if any (result should be integer string)
        if (str_contains($atomicAmount, '.')) {
            $atomicAmount = substr($atomicAmount, 0, strpos($atomicAmount, '.'));
        }

        return $atomicAmount;
    }

    /**
     * Convert an ESDT amount to its atomic unit based on its decimals.
     * NOTE: This requires fetching token decimals first (e.g., via TokenService).
     *
     * @param string|float|int $amountEsdt Amount in ESDT.
     * @param int $decimals The number of decimals for the specific ESDT token.
     * @return string Amount in atomic units as a string.
     */
    public static function esdtToAtomic(string|float|int $amountEsdt, int $decimals): string
    {
        if (!is_numeric($amountEsdt) || $amountEsdt < 0) {
            throw new InvalidArgumentException("Invalid ESDT amount provided.");
        }
        if ($decimals < 0) {
             throw new InvalidArgumentException("Decimals cannot be negative.");
        }

        $scale = $decimals;
        $multiplier = bcpow('10', (string)$scale, $scale);
        $atomicAmount = bcmul((string)$amountEsdt, $multiplier, $scale);

        if (str_contains($atomicAmount, '.')) {
            $atomicAmount = substr($atomicAmount, 0, strpos($atomicAmount, '.'));
        }

        return $atomicAmount;
    }

    /**
     * Encodes arguments for a smart contract call according to MultiversX specifications.
     *
     * @param array $args Array of arguments (string, int, bool, GMP, Bech32 address string).
     * @return array Array of hex-encoded arguments.
     * @throws InvalidArgumentException For unsupported argument types.
     */
    public static function encodeSmartContractArgs(array $args): array
    {
        $encodedArgs = [];
        foreach ($args as $arg) {
            if ($arg instanceof \GMP) {
                // Raw hex value from GMP
                $encodedArgs[] = gmp_strval($arg, 16);
            } elseif (is_string($arg)) {
                // Check if it's a Bech32 address first
                if (str_starts_with($arg, 'erd1') && strlen($arg) === 62) {
                    // Convert address to public key hex
                    try {
                        $encodedArgs[] = WalletService::bech32ToPublicKeyHex($arg);
                    } catch (\Exception $e) {
                        throw new InvalidArgumentException("Invalid Bech32 address provided as argument: {$arg} - " . $e->getMessage());
                    }
                } else {
                    // Otherwise, treat as a normal string and hex encode
                    $hexArg = bin2hex($arg);
                    $encodedArgs[] = $hexArg;
                }
            } elseif (is_int($arg)) {
                 // Raw hex value from int
                 $encodedArgs[] = dechex($arg);
            } elseif (is_bool($arg)) {
                // Boolean: 01 for true, 00 for false
                $encodedArgs[] = $arg ? '01' : '00';
            } else {
                // Add support for other types (e.g., List<T>) later if needed
                throw new InvalidArgumentException("Unsupported argument type encountered: " . gettype($arg));
            }
        }
        return $encodedArgs;
    }

     /**
     * Builds the data field string for a smart contract call.
     *
     * @param string $functionName The name of the function to call.
     * @param array $encodedArgs Hex-encoded arguments from encodeSmartContractArgs.
     * @return string The formatted data string (functionName@arg1@arg2...).
     */
    public static function buildContractDataField(string $functionName, array $encodedArgs): string
    {
        if (empty($functionName)) {
             throw new InvalidArgumentException('Function name cannot be empty when building data field.');
        }
        // Basic validation: ensure args are hex strings
        foreach ($encodedArgs as $arg) {
            if (!ctype_xdigit($arg)) {
                 throw new InvalidArgumentException('Encoded arguments must be hexadecimal strings.');
            }
        }

        $data = $functionName;
        if (!empty($encodedArgs)) {
            $data .= '@' . implode('@', $encodedArgs);
        }
        return $data;
    }
} 