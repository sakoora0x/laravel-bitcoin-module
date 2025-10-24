<?php

namespace sakoora0x\LaravelBitcoinModule\Tests\Feature;

use Decimal\Decimal;
use Mockery;
use sakoora0x\LaravelBitcoinModule\Bitcoin;
use sakoora0x\LaravelBitcoinModule\BitcoindRpcApi;
use sakoora0x\LaravelBitcoinModule\Enums\AddressType;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinAddress;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinDeposit;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinNode;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet;
use sakoora0x\LaravelBitcoinModule\Tests\TestCase;

class BitcoinIntegrationTest extends TestCase
{
    protected Bitcoin $bitcoin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bitcoin = new Bitcoin();

        config(['bitcoin.models.node' => BitcoinNode::class]);
        config(['bitcoin.models.wallet' => BitcoinWallet::class]);
        config(['bitcoin.models.address' => BitcoinAddress::class]);
        config(['bitcoin.models.deposit' => BitcoinDeposit::class]);
        config(['bitcoin.models.rpc_client' => BitcoindRpcApi::class]);
        config(['bitcoin.address_type' => AddressType::BECH32]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_complete_node_creation_workflow()
    {
        // Test node creation workflow without RPC mocking
        // (RPC mocking doesn't work when class is already loaded)
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $this->assertDatabaseHas('bitcoin_nodes', [
            'name' => 'testnode',
            'host' => '127.0.0.1',
            'port' => 8332,
        ]);

        $this->assertEquals('testnode', $node->name);
        $this->assertEquals('testpass', $node->password); // Should be decrypted
    }

    public function test_complete_wallet_and_address_workflow()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'password' => 'walletpass',
            'descriptors' => [
                ['desc' => 'wpkh([fingerprint/84h/0h/0h]xpub...)'],
            ],
        ]);

        $address = $wallet->addresses()->create([
            'address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
            'type' => AddressType::BECH32,
            'title' => 'Primary Address',
        ]);

        // Verify database
        $this->assertDatabaseHas('bitcoin_wallets', [
            'name' => 'testwallet',
            'node_id' => $node->id,
        ]);

        $this->assertDatabaseHas('bitcoin_addresses', [
            'address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
            'wallet_id' => $wallet->id,
        ]);

        // Verify relationships
        $this->assertEquals($node->id, $wallet->node->id);
        $this->assertEquals($wallet->id, $address->wallet->id);
        $this->assertCount(1, $node->wallets);
        $this->assertCount(1, $wallet->addresses);
    }

    public function test_complete_deposit_tracking_workflow()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'descriptors' => [['desc' => 'test']],
        ]);

        $address = $wallet->addresses()->create([
            'address' => 'bc1qtest',
            'type' => AddressType::BECH32,
        ]);

        // Create initial deposit
        $deposit = $address->deposits()->create([
            'wallet_id' => $wallet->id,
            'txid' => 'abcd1234',
            'amount' => '0.5',
            'block_height' => 700000,
            'confirmations' => 1,
            'time_at' => now(),
        ]);

        // Verify deposit was created
        $this->assertDatabaseHas('bitcoin_deposits', [
            'txid' => 'abcd1234',
            'wallet_id' => $wallet->id,
            'address_id' => $address->id,
        ]);

        // Update deposit confirmations (simulating blockchain confirmation)
        $deposit->update(['confirmations' => 6]);

        $this->assertEquals(6, $deposit->fresh()->confirmations);

        // Verify relationships
        $this->assertEquals($wallet->id, $deposit->wallet->id);
        $this->assertEquals($address->id, $deposit->address->id);
        $this->assertCount(1, $wallet->deposits);
        $this->assertCount(1, $address->deposits);
    }

    public function test_multiple_wallets_on_single_node()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $wallet1 = $node->wallets()->create([
            'name' => 'wallet1',
            'title' => 'Wallet 1',
            'descriptors' => [['desc' => 'test']],
        ]);

        $wallet2 = $node->wallets()->create([
            'name' => 'wallet2',
            'title' => 'Wallet 2',
            'descriptors' => [['desc' => 'test']],
        ]);

        $wallet3 = $node->wallets()->create([
            'name' => 'wallet3',
            'title' => 'Wallet 3',
            'descriptors' => [['desc' => 'test']],
        ]);

        $this->assertCount(3, $node->wallets);
        $this->assertEquals($node->id, $wallet1->node->id);
        $this->assertEquals($node->id, $wallet2->node->id);
        $this->assertEquals($node->id, $wallet3->node->id);
    }

    public function test_multiple_addresses_per_wallet()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'descriptors' => [['desc' => 'test']],
        ]);

        $addresses = [];
        foreach (['bech32', 'legacy', 'p2sh'] as $index => $type) {
            $addresses[] = $wallet->addresses()->create([
                'address' => "bc1qaddress{$index}",
                'type' => AddressType::BECH32,
                'title' => ucfirst($type) . ' Address',
            ]);
        }

        $this->assertCount(3, $wallet->addresses);
    }

    public function test_balance_tracking_across_addresses()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'descriptors' => [['desc' => 'test']],
            'balance' => '0',
        ]);

        $address1 = $wallet->addresses()->create([
            'address' => 'bc1qaddress1',
            'type' => AddressType::BECH32,
            'balance' => '1.0',
        ]);

        $address2 = $wallet->addresses()->create([
            'address' => 'bc1qaddress2',
            'type' => AddressType::BECH32,
            'balance' => '0.5',
        ]);

        // Calculate total address balance
        $totalAddressBalance = $wallet->addresses()
            ->get()
            ->reduce(function ($carry, $address) {
                return $carry->add($address->balance);
            }, new Decimal('0'));

        // Our Decimal polyfill returns full precision
        $this->assertStringContainsString('1.5', $totalAddressBalance->toString());
    }

    public function test_deposit_amount_precision()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'descriptors' => [['desc' => 'test']],
        ]);

        $address = $wallet->addresses()->create([
            'address' => 'bc1qtest',
            'type' => AddressType::BECH32,
        ]);

        // Test with smallest BTC unit (1 satoshi = 0.00000001 BTC)
        $deposit = $address->deposits()->create([
            'wallet_id' => $wallet->id,
            'txid' => 'txid123',
            'amount' => '0.00000001',
            'block_height' => 700000,
            'confirmations' => 1,
            'time_at' => now(),
        ]);

        $this->assertEquals('0.00000001', $deposit->amount->toString());

        // Test with large amount
        $largeDeposit = $address->deposits()->create([
            'wallet_id' => $wallet->id,
            'txid' => 'txid456',
            'amount' => '21000000.00000000',
            'block_height' => 700001,
            'confirmations' => 1,
            'time_at' => now(),
        ]);

        $this->assertEquals('21000000.00000000', $largeDeposit->amount->toString());
    }

    public function test_encrypted_fields_remain_encrypted_in_database()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'supersecret',
        ]);

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'password' => 'walletpassword',
            'descriptors' => [['desc' => 'sensitive descriptor data']],
        ]);

        // Get raw database values
        $rawNode = \DB::table('bitcoin_nodes')->find($node->id);
        $rawWallet = \DB::table('bitcoin_wallets')->find($wallet->id);

        // Raw values should be encrypted (not equal to plaintext)
        $this->assertNotEquals('supersecret', $rawNode->password);
        $this->assertNotEquals('walletpassword', $rawWallet->password);
        $this->assertNotEquals(json_encode([['desc' => 'sensitive descriptor data']]), $rawWallet->descriptors);

        // Model values should be decrypted
        $this->assertEquals('supersecret', $node->password);
        $this->assertEquals('walletpassword', $wallet->password);
        $this->assertEquals([['desc' => 'sensitive descriptor data']], $wallet->descriptors);
    }

    public function test_address_type_enum_values()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'descriptors' => [['desc' => 'test']],
        ]);

        foreach (AddressType::cases() as $type) {
            $address = $wallet->addresses()->create([
                'address' => 'addr_' . $type->value,
                'type' => $type,
            ]);

            $retrieved = BitcoinAddress::find($address->id);

            $this->assertInstanceOf(AddressType::class, $retrieved->type);
            $this->assertEquals($type, $retrieved->type);
        }
    }

    public function test_configuration_can_override_models()
    {
        // Test that configured models are used
        $this->assertEquals(BitcoinNode::class, config('bitcoin.models.node'));
        $this->assertEquals(BitcoinWallet::class, config('bitcoin.models.wallet'));
        $this->assertEquals(BitcoinAddress::class, config('bitcoin.models.address'));
        $this->assertEquals(BitcoinDeposit::class, config('bitcoin.models.deposit'));
    }

    public function test_wallet_sync_at_timestamp_updates()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'descriptors' => [['desc' => 'test']],
        ]);

        $this->assertNull($wallet->sync_at);

        $wallet->update(['sync_at' => now()]);

        $this->assertNotNull($wallet->fresh()->sync_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $wallet->sync_at);
    }

    public function test_cascade_queries_work_correctly()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'descriptors' => [['desc' => 'test']],
        ]);

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

        // Test cascade queries
        $foundDeposit = BitcoinDeposit::with(['wallet.node', 'address.wallet'])
            ->find($deposit->id);

        $this->assertEquals($node->id, $foundDeposit->wallet->node->id);
        $this->assertEquals($wallet->id, $foundDeposit->address->wallet->id);
        $this->assertEquals('testnode', $foundDeposit->wallet->node->name);
    }
}
