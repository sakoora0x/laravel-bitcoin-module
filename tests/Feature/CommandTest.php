<?php

namespace sakoora0x\LaravelBitcoinModule\Tests\Feature;

use Mockery;
use sakoora0x\LaravelBitcoinModule\Commands\BitcoinSyncCommand;
use sakoora0x\LaravelBitcoinModule\Commands\BitcoinSyncWalletCommand;
use sakoora0x\LaravelBitcoinModule\Commands\BitcoinWebhookCommand;
use sakoora0x\LaravelBitcoinModule\Enums\AddressType;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinAddress;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinDeposit;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinNode;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet;
use sakoora0x\LaravelBitcoinModule\Services\SyncService;
use sakoora0x\LaravelBitcoinModule\Tests\TestCase;
use sakoora0x\LaravelBitcoinModule\WebhookHandlers\WebhookHandlerInterface;

class CommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['bitcoin.models.node' => BitcoinNode::class]);
        config(['bitcoin.models.wallet' => BitcoinWallet::class]);
        config(['bitcoin.models.address' => BitcoinAddress::class]);
        config(['bitcoin.models.deposit' => BitcoinDeposit::class]);
        config(['bitcoin.webhook_handler' => \sakoora0x\LaravelBitcoinModule\WebhookHandlers\EmptyWebhookHandler::class]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createTestWallet(string $name = 'testwallet'): BitcoinWallet
    {
        // Try to find existing node or create new one
        $node = BitcoinNode::where('name', 'testnode')->first();

        if (!$node) {
            $node = BitcoinNode::create([
                'name' => 'testnode',
                'title' => 'Test Node',
                'host' => '127.0.0.1',
                'port' => 8332,
                'username' => 'testuser',
                'password' => 'testpass',
            ]);
        }

        return $node->wallets()->create([
            'name' => $name,
            'title' => 'Test Wallet',
            'descriptors' => [['desc' => 'test']],
        ]);
    }

    public function test_bitcoin_sync_command_syncs_all_wallets()
    {
        $wallet1 = $this->createTestWallet('wallet1');
        $wallet2 = $this->createTestWallet('wallet2');

        // Mock SyncService
        $mockService = Mockery::mock(SyncService::class);
        $mockService->shouldReceive('run')->twice();

        $this->app->bind(SyncService::class, function($app, $params) use ($mockService) {
            return $mockService;
        });

        $this->artisan('bitcoin:sync')
            ->expectsOutput('Bitcoin Wallet wallet1 starting sync...')
            ->expectsOutput('Bitcoin Wallet wallet1 successfully sync finished!')
            ->expectsOutput('Bitcoin Wallet wallet2 starting sync...')
            ->expectsOutput('Bitcoin Wallet wallet2 successfully sync finished!')
            ->assertSuccessful();
    }

    public function test_bitcoin_sync_command_handles_errors_gracefully()
    {
        $wallet = $this->createTestWallet('testwallet');

        // Mock SyncService to throw exception
        $mockService = Mockery::mock(SyncService::class);
        $mockService->shouldReceive('run')->andThrow(new \Exception('Test error'));

        $this->app->bind(SyncService::class, function($app, $params) use ($mockService) {
            return $mockService;
        });

        $this->artisan('bitcoin:sync')
            ->expectsOutput('Bitcoin Wallet testwallet starting sync...')
            ->expectsOutput('Error: Test error')
            ->assertSuccessful(); // Command should not fail even if sync fails
    }

    public function test_bitcoin_sync_command_works_with_no_wallets()
    {
        $this->artisan('bitcoin:sync')
            ->assertSuccessful();
    }

    public function test_bitcoin_sync_wallet_command_syncs_specific_wallet()
    {
        $wallet = $this->createTestWallet('testwallet');

        // Mock SyncService
        $mockService = Mockery::mock(SyncService::class);
        $mockService->shouldReceive('run')->once();

        $this->app->bind(SyncService::class, function($app, $params) use ($mockService) {
            return $mockService;
        });

        $this->artisan('bitcoin:sync-wallet', ['wallet_id' => $wallet->id])
            ->expectsOutput('Bitcoin Wallet testwallet starting sync...')
            ->expectsOutput('Bitcoin Wallet testwallet successfully sync finished!')
            ->assertSuccessful();
    }

    public function test_bitcoin_sync_wallet_command_fails_with_invalid_wallet_id()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->artisan('bitcoin:sync-wallet', ['wallet_id' => 99999]);
    }

    public function test_bitcoin_sync_wallet_command_handles_sync_errors()
    {
        $wallet = $this->createTestWallet('testwallet');

        // Mock SyncService to throw exception
        $mockService = Mockery::mock(SyncService::class);
        $mockService->shouldReceive('run')->andThrow(new \Exception('Sync failed'));

        $this->app->bind(SyncService::class, function($app, $params) use ($mockService) {
            return $mockService;
        });

        $this->artisan('bitcoin:sync-wallet', ['wallet_id' => $wallet->id])
            ->expectsOutput('Bitcoin Wallet testwallet starting sync...')
            ->expectsOutput('Error: Sync failed')
            ->assertSuccessful();
    }

    public function test_bitcoin_webhook_command_executes_webhook_handler()
    {
        $wallet = $this->createTestWallet('testwallet');

        $address = $wallet->addresses()->create([
            'address' => 'bc1qtest',
            'type' => AddressType::BECH32,
        ]);

        $deposit = $address->deposits()->create([
            'wallet_id' => $wallet->id,
            'txid' => 'txid123',
            'amount' => '0.1',
            'block_height' => 700000,
            'confirmations' => 1,
            'time_at' => now(),
        ]);

        // Mock webhook handler
        $mockHandler = Mockery::mock(WebhookHandlerInterface::class);
        $mockHandler->shouldReceive('handle')
            ->once()
            ->with(
                Mockery::on(function($arg) use ($wallet) {
                    return $arg->id === $wallet->id;
                }),
                Mockery::on(function($arg) use ($address) {
                    return $arg->id === $address->id;
                }),
                Mockery::on(function($arg) use ($deposit) {
                    return $arg->id === $deposit->id;
                })
            );

        $this->app->bind(get_class($mockHandler), function() use ($mockHandler) {
            return $mockHandler;
        });
        config(['bitcoin.webhook_handler' => get_class($mockHandler)]);

        $this->artisan('bitcoin:webhook', ['deposit_id' => $deposit->id])
            ->expectsOutput('Webhook successfully execute!')
            ->assertSuccessful();
    }

    public function test_bitcoin_webhook_command_fails_with_invalid_deposit_id()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->artisan('bitcoin:webhook', ['deposit_id' => 99999]);
    }

    public function test_bitcoin_webhook_command_loads_relationships()
    {
        $wallet = $this->createTestWallet('testwallet');

        $address = $wallet->addresses()->create([
            'address' => 'bc1qtest',
            'type' => AddressType::BECH32,
        ]);

        $deposit = $address->deposits()->create([
            'wallet_id' => $wallet->id,
            'txid' => 'txid123',
            'amount' => '0.1',
            'block_height' => 700000,
            'confirmations' => 1,
            'time_at' => now(),
        ]);

        // Mock webhook handler that verifies relationships are loaded
        $mockHandler = Mockery::mock(WebhookHandlerInterface::class);
        $mockHandler->shouldReceive('handle')
            ->once()
            ->with(
                Mockery::on(function($walletArg) {
                    return $walletArg instanceof BitcoinWallet && $walletArg->exists;
                }),
                Mockery::on(function($addressArg) {
                    return $addressArg instanceof BitcoinAddress && $addressArg->exists;
                }),
                Mockery::on(function($depositArg) {
                    return $depositArg instanceof BitcoinDeposit && $depositArg->exists;
                })
            );

        $this->app->bind(get_class($mockHandler), function() use ($mockHandler) {
            return $mockHandler;
        });
        config(['bitcoin.webhook_handler' => get_class($mockHandler)]);

        $this->artisan('bitcoin:webhook', ['deposit_id' => $deposit->id])
            ->assertSuccessful();
    }

    public function test_commands_are_registered()
    {
        // Verify commands are available
        $commands = \Artisan::all();

        $this->assertArrayHasKey('bitcoin:sync', $commands);
        $this->assertArrayHasKey('bitcoin:sync-wallet', $commands);
        $this->assertArrayHasKey('bitcoin:webhook', $commands);
    }

    public function test_bitcoin_sync_command_signature_is_correct()
    {
        $command = new BitcoinSyncCommand();

        $this->assertEquals('bitcoin:sync', $command->getName());
        $this->assertEquals('Bitcoin sync wallets', $command->getDescription());
    }

    public function test_bitcoin_sync_wallet_command_signature_is_correct()
    {
        $command = new BitcoinSyncWalletCommand();

        $this->assertEquals('bitcoin:sync-wallet', $command->getName());
        $this->assertEquals('Sync Bitcoin Wallet', $command->getDescription());
    }

    public function test_bitcoin_webhook_command_signature_is_correct()
    {
        $command = new BitcoinWebhookCommand();

        $this->assertEquals('bitcoin:webhook', $command->getName());
        $this->assertEquals('Bitcoin deposit webhook handler', $command->getDescription());
    }
}
