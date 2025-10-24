<?php

return [
    /*
     * Sets the handler to be used when Bitcoin Wallet has a new deposit.
     */
    'webhook_handler' => \sakoora0x\LaravelBitcoinModule\WebhookHandlers\EmptyWebhookHandler::class,

    /*
     * Set address type of generate new addresses.
     */
    'address_type' => \sakoora0x\LaravelBitcoinModule\Enums\AddressType::BECH32,

    /*
     * Set model class for both BitcoinWallet, BitcoinAddress, BitcoinDeposit,
     * to allow more customization.
     *
     * BitcoindRpcApi model must be or extend `sakoora0x\LaravelBitcoinModule\BitcoindRpcApi::class`
     * BitcoinNode model must be or extend `sakoora0x\LaravelBitcoinModule\Models\BitcoinNode::class`
     * BitcoinWallet model must be or extend `sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet::class`
     * BitcoinAddress model must be or extend `sakoora0x\LaravelBitcoinModule\Models\BitcoinAddress::class`
     * BitcoinDeposit model must be or extend `sakoora0x\LaravelBitcoinModule\Models\BitcoinDeposit::class`
     */
    'models' => [
        'rpc_client' => \sakoora0x\LaravelBitcoinModule\BitcoindRpcApi::class,
        'node' => \sakoora0x\LaravelBitcoinModule\Models\BitcoinNode::class,
        'wallet' => \sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet::class,
        'address' => \sakoora0x\LaravelBitcoinModule\Models\BitcoinAddress::class,
        'deposit' => \sakoora0x\LaravelBitcoinModule\Models\BitcoinDeposit::class,
    ],
];
