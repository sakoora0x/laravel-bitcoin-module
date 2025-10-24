<?php

namespace sakoora0x\LaravelBitcoinModule\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet;
use sakoora0x\LaravelBitcoinModule\Services\SyncService;

class BitcoinSyncWalletCommand extends Command
{
    protected $signature = 'bitcoin:sync-wallet {wallet_id}';

    protected $description = 'Sync Bitcoin Wallet';

    public function handle(): void
    {
        $walletId = $this->argument('wallet_id');

        /** @var class-string<BitcoinWallet> $model */
        $model = config('bitcoin.models.wallet');
        $wallet = $model::findOrFail($walletId);

        $this->info("Bitcoin Wallet $wallet->name starting sync...");

        try {
            App::make(SyncService::class, [
                'wallet' => $wallet
            ])->run();

            $this->info("Bitcoin Wallet $wallet->name successfully sync finished!");
        }
        catch(\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
        }
    }
}
