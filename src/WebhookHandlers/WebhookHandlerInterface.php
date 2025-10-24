<?php

namespace sakoora0x\LaravelBitcoinModule\WebhookHandlers;

use sakoora0x\LaravelBitcoinModule\Models\BitcoinAddress;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinDeposit;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet;

interface WebhookHandlerInterface
{
    public function handle(BitcoinWallet $wallet, BitcoinAddress $address, BitcoinDeposit $deposit): void;
}