<?php

namespace sakoora0x\LaravelBitcoinModule\Tests\Feature;

use Decimal\Decimal;
use sakoora0x\LaravelBitcoinModule\Enums\AddressType;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinAddress;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinDeposit;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinNode;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet;
use sakoora0x\LaravelBitcoinModule\Tests\TestCase;

class ModelTest extends TestCase
{
    public function test_bitcoin_node_can_be_created()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $this->assertInstanceOf(BitcoinNode::class, $node);
        $this->assertEquals('testnode', $node->name);
        $this->assertEquals('Test Node', $node->title);
        $this->assertEquals('127.0.0.1', $node->host);
        $this->assertEquals(8332, $node->port);
        $this->assertEquals('testuser', $node->username);
        $this->assertEquals('testpass', $node->password);
    }

    public function test_bitcoin_node_encrypts_password()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'secretpassword',
        ]);

        // Retrieve from database
        $retrieved = BitcoinNode::find($node->id);

        // Password should be decrypted when accessed
        $this->assertEquals('secretpassword', $retrieved->password);

        // But stored encrypted in database
        $raw = \DB::table('bitcoin_nodes')->where('id', $node->id)->first();
        $this->assertNotEquals('secretpassword', $raw->password);
    }

    public function test_bitcoin_node_hides_password_in_json()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'secretpassword',
        ]);

        $json = $node->toArray();

        $this->assertArrayNotHasKey('password', $json);
    }

    public function test_bitcoin_node_has_wallets_relationship()
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

        $this->assertInstanceOf(BitcoinWallet::class, $wallet);
        $this->assertEquals($node->id, $wallet->node_id);
        $this->assertCount(1, $node->wallets);
    }

    public function test_bitcoin_node_creates_api_instance()
    {
        config(['bitcoin.models.rpc_client' => \sakoora0x\LaravelBitcoinModule\BitcoindRpcApi::class]);

        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $api = $node->api();

        $this->assertInstanceOf(\sakoora0x\LaravelBitcoinModule\BitcoindRpcApi::class, $api);
    }

    public function test_bitcoin_wallet_can_be_created()
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
            'descriptors' => [['desc' => 'descriptor1']],
        ]);

        $this->assertInstanceOf(BitcoinWallet::class, $wallet);
        $this->assertEquals('testwallet', $wallet->name);
        $this->assertEquals('Test Wallet', $wallet->title);
        $this->assertEquals('walletpass', $wallet->password);
        $this->assertIsArray($wallet->descriptors);
    }

    public function test_bitcoin_wallet_encrypts_password_and_descriptors()
    {
        $node = BitcoinNode::create([
            'name' => 'testnode',
            'title' => 'Test Node',
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'testuser',
            'password' => 'testpass',
        ]);

        $descriptors = [['desc' => 'wpkh([fingerprint/84h/0h/0h]xpub...)']];

        $wallet = $node->wallets()->create([
            'name' => 'testwallet',
            'title' => 'Test Wallet',
            'password' => 'secretpass',
            'descriptors' => $descriptors,
        ]);

        // Retrieve from database
        $retrieved = BitcoinWallet::find($wallet->id);

        // Should be decrypted when accessed
        $this->assertEquals('secretpass', $retrieved->password);
        $this->assertEquals($descriptors, $retrieved->descriptors);

        // But stored encrypted in database
        $raw = \DB::table('bitcoin_wallets')->where('id', $wallet->id)->first();
        $this->assertNotEquals('secretpass', $raw->password);
        $this->assertNotEquals(json_encode($descriptors), $raw->descriptors);
    }

    public function test_bitcoin_wallet_casts_balance_to_decimal()
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
            'balance' => '1.50000000',
            'unconfirmed_balance' => '0.25000000',
        ]);

        $this->assertInstanceOf(Decimal::class, $wallet->balance);
        $this->assertInstanceOf(Decimal::class, $wallet->unconfirmed_balance);
        $this->assertEquals('1.50000000', $wallet->balance->toString());
        $this->assertEquals('0.25000000', $wallet->unconfirmed_balance->toString());
    }

    public function test_bitcoin_wallet_has_node_relationship()
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

        $this->assertInstanceOf(BitcoinNode::class, $wallet->node);
        $this->assertEquals($node->id, $wallet->node->id);
    }

    public function test_bitcoin_address_can_be_created()
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
            'address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
            'type' => AddressType::BECH32,
            'title' => 'Primary Address',
            'balance' => '0.50000000',
            'unconfirmed_balance' => '0.10000000',
        ]);

        $this->assertInstanceOf(BitcoinAddress::class, $address);
        $this->assertEquals('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', $address->address);
        $this->assertEquals(AddressType::BECH32, $address->type);
        $this->assertEquals('Primary Address', $address->title);
    }

    public function test_bitcoin_address_casts_type_to_enum()
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

        $this->assertInstanceOf(AddressType::class, $address->type);
        $this->assertEquals(AddressType::BECH32, $address->type);
    }

    public function test_bitcoin_address_casts_balance_to_decimal()
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
            'balance' => '2.00000000',
            'unconfirmed_balance' => '0.50000000',
        ]);

        $this->assertInstanceOf(Decimal::class, $address->balance);
        $this->assertInstanceOf(Decimal::class, $address->unconfirmed_balance);
        $this->assertEquals('2.00000000', $address->balance->toString());
        $this->assertEquals('0.50000000', $address->unconfirmed_balance->toString());
    }

    public function test_bitcoin_deposit_can_be_created()
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

        $deposit = $wallet->deposits()->create([
            'address_id' => $address->id,
            'txid' => 'abcdef1234567890',
            'amount' => '0.05000000',
            'block_height' => 700000,
            'confirmations' => 6,
            'time_at' => now(),
        ]);

        $this->assertInstanceOf(BitcoinDeposit::class, $deposit);
        $this->assertEquals('abcdef1234567890', $deposit->txid);
        $this->assertInstanceOf(Decimal::class, $deposit->amount);
        $this->assertEquals('0.05000000', $deposit->amount->toString());
        $this->assertEquals(6, $deposit->confirmations);
    }

    public function test_bitcoin_deposit_has_wallet_and_address_relationships()
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

        $deposit = $wallet->deposits()->create([
            'address_id' => $address->id,
            'txid' => 'abcdef1234567890',
            'amount' => '0.05000000',
            'block_height' => 700000,
            'confirmations' => 6,
            'time_at' => now(),
        ]);

        $this->assertInstanceOf(BitcoinWallet::class, $deposit->wallet);
        $this->assertInstanceOf(BitcoinAddress::class, $deposit->address);
        $this->assertEquals($wallet->id, $deposit->wallet->id);
        $this->assertEquals($address->id, $deposit->address->id);
    }

    public function test_relationships_cascade_correctly()
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
            'txid' => 'abcdef1234567890',
            'amount' => '0.05000000',
            'block_height' => 700000,
            'confirmations' => 6,
            'time_at' => now(),
        ]);

        // Verify the full chain
        $this->assertEquals($node->id, $wallet->node->id);
        $this->assertEquals($wallet->id, $address->wallet->id);
        $this->assertEquals($address->id, $deposit->address->id);
        $this->assertEquals($wallet->id, $deposit->wallet->id);
    }
}
