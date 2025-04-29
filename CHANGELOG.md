# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-11-15

### Added

*   **Initial Release of the MultiversX SDK for Laravel.**
*   **Laravel Integration:**
    *   `MultiversxServiceProvider` for service registration.
    *   Publishable configuration (`config/multiversx.php`) for network, API URL, and default transaction parameters.
    *   `Multiversx` Facade for simple access (defaults to `WalletService`).
*   **HTTP Client (`MultiversxClient`):**
    *   Handles GET/POST communication with the MultiversX API.
    *   Basic error handling with `MultiversxException`.
*   **Wallet Management (`WalletService`):**
    *   Generation of new key pairs (private/public) and MultiversX Bech32 addresses (`erd1...`) using `simplito/elliptic-php`.
    *   Address format and checksum validation (`isValidAddress`).
    *   Fetching account details from the API (`getAccount`).
    *   Static utility to convert a valid Bech32 address to its public key hex (`bech32ToPublicKeyHex`).
*   **Transaction Management (`TransactionService`):**
    *   Preparation of transaction structure for EGLD transfers (via `prepareSmartContractCall`).
    *   Preparation of transaction structure for ESDT transfers (`prepareEsdtTransfer`).
    *   Preparation of transaction structure for NFT/SFT transfers (`prepareNftTransfer`).
    *   Preparation of transaction structure for Smart Contract calls (`prepareSmartContractCall`), including argument encoding via `Converter`.
    *   Signing prepared transactions (`signTransaction`) using `simplito/elliptic-php` and `kornrunner/keccak`.
    *   Sending signed transactions to the API (`sendTransaction`).
    *   Fetching transaction status via its hash (`getTransactionStatus`).
*   **Token Management (`TokenService`):**
    *   Fetching properties of a specific token (ESDT, NFT, SFT) by its identifier (`getTokenProperties`).
    *   Fetching the balance of a specific token for an address (`getTokenBalance`).
    *   Listing all tokens held by an address with pagination (`listAccountTokens`).
*   **Utilities (`Utils/Converter`):**
    *   Conversion of EGLD amounts to atomic units (`egldToAtomic`) using BCMath.
    *   Conversion of ESDT amounts to atomic units (`esdtToAtomic`) using BCMath (requires token decimals).
    *   Encoding arguments for smart contract calls (`encodeSmartContractArgs`).
    *   Building the formatted `data` field for smart contract calls (`buildContractDataField`).
*   **Tests:**
    *   PHPUnit test suite covering features of services (`WalletService`, `TransactionService`, `TokenService`) and utilities (`Converter`).
*   **`.gitignore` file** to exclude unnecessary files from version control. 