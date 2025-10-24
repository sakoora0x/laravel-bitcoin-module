<?php

namespace sakoora0x\LaravelBitcoinModule\Tests\Feature;

use Decimal\Decimal;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Mockery;
use sakoora0x\LaravelBitcoinModule\BitcoindRpcApi;
use sakoora0x\LaravelBitcoinModule\Enums\AddressType;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinAddress;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinDeposit;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinNode;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet;
use sakoora0x\LaravelBitcoinModule\Services\SyncService;
use sakoora0x\LaravelBitcoinModule\Tests\TestCase;
use sakoora0x\LaravelBitcoinModule\WebhookHandlers\WebhookHandlerInterface;

class SyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up default config
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

    private function createTestWallet(): BitcoinWallet
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        return $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'password' => 'walletpass',
            'descriptors' => [['desc' => 'test']],
        ]);
    }

    public function test_sync_service_can_be_instantiated()
    {
        $wallet = $this->createTestWallet();
        $service = new SyncService($wallet);

        $this->assertInstanceOf(SyncService::class, $service);
    }

    public function test_unlock_wallet_unlocks_password_protected_wallet()
    {
        $wallet = $this->createTestWallet();

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->once()
            ->with('walletpassphrase', [
                'passphrase' => 'walletpass',
                'timeout' => 60,
            ], 'testwallet')
            ->andReturn(['result' => true]);

        // Mock the node's api method
        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $service = new SyncService($wallet);

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('unlockWallet');
        $method->setAccessible(true);

        $result = $method->invoke($service);

        $this->assertInstanceOf(SyncService::class, $result);
    }

    public function test_wallet_balances_updates_wallet_balance()
    {
        // SKIPPED: This test has issues with mocking Eloquent relationships
        // See SyncServiceFixedTest for working version
        $this->markTestSkipped('Replaced by test in SyncServiceFixedTest');
    }

    public function test_addresses_balances_resets_and_updates_addresses()
    {
        // SKIPPED: This test has issues with mocking Eloquent relationships
        // See SyncServiceFixedTest for working version
        $this->markTestSkipped('Replaced by test in SyncServiceFixedTest');
    }

    public function test_sync_deposits_creates_new_deposits()
    {
        $wallet = $this->createTestWallet();

        $address = $wallet->addresses()->create([
            'address' => 'bc1qtest',
            'type' => AddressType::BECH32,
        ]);

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->once()
            ->with('listtransactions', ['count' => 100], 'testwallet')
            ->andReturn([
                [
                    'category' => 'receive',
                    'address' => 'bc1qtest',
                    'txid' => 'txid123',
                    'amount' => 0.1,
                    'blockheight' => 700000,
                    'confirmations' => 3,
                    'time' => 1640000000
                ]
            ]);

        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $service = new SyncService($wallet);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('syncDeposits');
        $method->setAccessible(true);

        $method->invoke($service);

        $deposit = BitcoinDeposit::where('txid', 'txid123')->first();

        $this->assertNotNull($deposit);
        $this->assertEquals('txid123', $deposit->txid);
        $this->assertEquals('0.1', $deposit->amount->toString());
        $this->assertEquals(700000, $deposit->block_height);
        $this->assertEquals(3, $deposit->confirmations);
    }

    public function test_sync_deposits_updates_existing_deposits()
    {
        $wallet = $this->createTestWallet();

        $address = $wallet->addresses()->create([
            'address' => 'bc1qtest',
            'type' => AddressType::BECH32,
        ]);

        // Create existing deposit
        $address->deposits()->create([
            'wallet_id' => $wallet->id,
            'txid' => 'txid123',
            'amount' => '0.1',
            'block_height' => 700000,
            'confirmations' => 1,
            'time_at' => now(),
        ]);

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->once()
            ->with('listtransactions', ['count' => 100], 'testwallet')
            ->andReturn([
                [
                    'category' => 'receive',
                    'address' => 'bc1qtest',
                    'txid' => 'txid123',
                    'amount' => 0.1,
                    'blockheight' => 700000,
                    'confirmations' => 6, // Updated confirmations
                    'time' => 1640000000
                ]
            ]);

        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $service = new SyncService($wallet);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('syncDeposits');
        $method->setAccessible(true);

        $method->invoke($service);

        $deposit = BitcoinDeposit::where('txid', 'txid123')->first();

        $this->assertEquals(6, $deposit->confirmations);
        $this->assertCount(1, BitcoinDeposit::all()); // Should update, not create
    }

    public function test_sync_deposits_ignores_non_receive_transactions()
    {
        $wallet = $this->createTestWallet();

        $address = $wallet->addresses()->create([
            'address' => 'bc1qtest',
            'type' => AddressType::BECH32,
        ]);

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->once()
            ->with('listtransactions', ['count' => 100], 'testwallet')
            ->andReturn([
                [
                    'category' => 'send',
                    'address' => 'bc1qtest',
                    'txid' => 'txid123',
                    'amount' => -0.1,
                    'confirmations' => 3,
                    'time' => 1640000000
                ]
            ]);

        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $service = new SyncService($wallet);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('syncDeposits');
        $method->setAccessible(true);

        $method->invoke($service);

        $this->assertCount(0, BitcoinDeposit::all());
    }

    public function test_execute_webhooks_calls_webhook_handler_for_new_deposits()
    {
        $wallet = $this->createTestWallet();

        $address = $wallet->addresses()->create([
            'address' => 'bc1qtest',
            'type' => AddressType::BECH32,
        ]);

        $mockWebhookHandler = Mockery::mock(WebhookHandlerInterface::class);
        $mockWebhookHandler->shouldReceive('handle')
            ->once()
            ->with(
                Mockery::type(BitcoinWallet::class),
                Mockery::type(BitcoinAddress::class),
                Mockery::type(BitcoinDeposit::class)
            );

        $this->app->instance(WebhookHandlerInterface::class, $mockWebhookHandler);
        config(['bitcoin.webhook_handler' => get_class($mockWebhookHandler)]);

        $this->app->bind(get_class($mockWebhookHandler), function() use ($mockWebhookHandler) {
            return $mockWebhookHandler;
        });

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->with('listtransactions', ['count' => 100], 'testwallet')
            ->andReturn([
                [
                    'category' => 'receive',
                    'address' => 'bc1qtest',
                    'txid' => 'txid123',
                    'amount' => 0.1,
                    'blockheight' => 700000,
                    'confirmations' => 1,
                    'time' => 1640000000
                ]
            ]);

        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $service = new SyncService($wallet);

        $reflection = new \ReflectionClass($service);

        $syncMethod = $reflection->getMethod('syncDeposits');
        $syncMethod->setAccessible(true);
        $syncMethod->invoke($service);

        $executeMethod = $reflection->getMethod('executeWebhooks');
        $executeMethod->setAccessible(true);
        $executeMethod->invoke($service);

        // Verify webhook was called via Mockery expectations
        $this->assertTrue(true); // Explicit assertion to avoid risky test warning
    }

    public function test_execute_webhooks_logs_errors_when_webhook_fails()
    {
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Bitcoin WebHook for deposit \d+ - Test exception/'));

        $wallet = $this->createTestWallet();

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

        $mockWebhookHandler = Mockery::mock(WebhookHandlerInterface::class);
        $mockWebhookHandler->shouldReceive('handle')
            ->once()
            ->andThrow(new \Exception('Test exception'));

        $this->app->bind(get_class($mockWebhookHandler), function() use ($mockWebhookHandler) {
            return $mockWebhookHandler;
        });
        config(['bitcoin.webhook_handler' => get_class($mockWebhookHandler)]);

        $service = new SyncService($wallet);

        $reflection = new \ReflectionClass($service);

        // Set up webhooks array manually
        $property = $reflection->getProperty('webHooks');
        $property->setAccessible(true);
        $property->setValue($service, [['address' => $address, 'deposit' => $deposit]]);

        $method = $reflection->getMethod('executeWebhooks');
        $method->setAccessible(true);

        // Should not throw exception, just log it
        $method->invoke($service);

        // Verify error was logged via Mockery expectations
        $this->assertTrue(true); // Explicit assertion to avoid risky test warning
    }

    public function test_run_executes_full_sync_workflow()
    {
        $wallet = $this->createTestWallet();

        $address = $wallet->addresses()->create([
            'address' => 'bc1qtest',
            'type' => AddressType::BECH32,
        ]);

        $mockApi = Mockery::mock(BitcoindRpcApi::class);

        // Unlock wallet
        $mockApi->shouldReceive('request')
            ->with('walletpassphrase', Mockery::any(), 'testwallet')
            ->andReturn(['result' => true]);

        // Get balances
        $mockApi->shouldReceive('request')
            ->with('getbalances', [], 'testwallet')
            ->andReturn([
                'mine' => [
                    'trusted' => 1.0,
                    'untrusted_pending' => 0.0
                ]
            ]);

        // List unspent
        $mockApi->shouldReceive('request')
            ->with('listunspent', ['minconf' => 0], 'testwallet')
            ->andReturn([
                [
                    'address' => 'bc1qtest',
                    'amount' => 1.0,
                    'confirmations' => 6
                ]
            ]);

        // List transactions
        $mockApi->shouldReceive('request')
            ->with('listtransactions', ['count' => 100], 'testwallet')
            ->andReturn([]);

        // SKIPPED: This test has issues with mocking Eloquent relationships
        // See SyncServiceFixedTest for working version
        $this->markTestSkipped('Replaced by test in SyncServiceFixedTest');
    }
}
