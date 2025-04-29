<?php

return [
    /* Blockchain environment: 'mainnet', 'testnet', or 'devnet' */
    'network' => env('MULTIVERSX_NETWORK', 'mainnet'),

    /* REST API URL */
    'api_url' => env('MULTIVERSX_API_URL', 'https://api.multiversx.com'),

    /* Default gas price for transactions */
    'default_gas_price' => env('MULTIVERSX_DEFAULT_GAS_PRICE', 1000000000),

    /* Default transaction version */
    'default_tx_version' => env('MULTIVERSX_DEFAULT_TX_VERSION', 1),
]; 