<?php

namespace sakoora0x\LaravelBitcoinModule\WebhookHandlers;

use Illuminate\Support\Facades\Log;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinAddress;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinDeposit;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet;

class EmptyWebhookHandler implements WebhookHandlerInterface
{
    public function handle(BitcoinWallet $wallet, BitcoinAddress $address, BitcoinDeposit $deposit): void
    {
        Log::error('Bitcoin Wallet '.$wallet->name.' new transaction '.$deposit->txid.' for address '.$address->address);
    }
}