<?php

namespace sakoora0x\LaravelBitcoinModule\Tests\Unit;

use Decimal\Decimal;
use Mockery;
use sakoora0x\LaravelBitcoinModule\Bitcoin;
use sakoora0x\LaravelBitcoinModule\BitcoindRpcApi;
use sakoora0x\LaravelBitcoinModule\Enums\AddressType;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinNode;
use sakoora0x\LaravelBitcoinModule\Models\BitcoinWallet;
use sakoora0x\LaravelBitcoinModule\Tests\TestCase;

class BitcoinTest extends TestCase
{
    protected Bitcoin $bitcoin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bitcoin = new Bitcoin();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_validate_address_returns_null_for_invalid_address()
    {
        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->once()
            ->with('validateaddress', ['address' => 'invalid'])
            ->andReturn(['isvalid' => false]);

        $node = Mockery::mock(BitcoinNode::class);
        $node->shouldReceive('api')->andReturn($mockApi);

        $result = $this->bitcoin->validateAddress($node, 'invalid');

        $this->assertNull($result);
    }

    public function test_validate_address_returns_bech32_for_witness_v0()
    {
        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->once()
            ->with('validateaddress', ['address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'])
            ->andReturn([
                'isvalid' => true,
                'iswitness' => true,
                'witness_version' => 0
            ]);

        $node = Mockery::mock(BitcoinNode::class);
        $node->shouldReceive('api')->andReturn($mockApi);

        $result = $this->bitcoin->validateAddress($node, 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq');

        $this->assertEquals(AddressType::BECH32, $result);
    }

    public function test_validate_address_returns_bech32m_for_witness_v1()
    {
        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->once()
            ->with('validateaddress', ['address' => 'bc1p5cyxnuxmeuwuvkwfem96lqzszd02n6xdcjrs20cac6yqjjwudpxqkedrcr'])
            ->andReturn([
                'isvalid' => true,
                'iswitness' => true,
                'witness_version' => 1
            ]);

        $node = Mockery::mock(BitcoinNode::class);
        $node->shouldReceive('api')->andReturn($mockApi);

        $result = $this->bitcoin->validateAddress($node, 'bc1p5cyxnuxmeuwuvkwfem96lqzszd02n6xdcjrs20cac6yqjjwudpxqkedrcr');

        $this->assertEquals(AddressType::BECH32M, $result);
    }

    public function test_validate_address_returns_p2sh_segwit_for_script()
    {
        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->once()
            ->with('validateaddress', ['address' => '3J98t1WpEZ73CNmYviecrnyiWrnqRhWNLy'])
            ->andReturn([
                'isvalid' => true,
                'iswitness' => false,
                'isscript' => true
            ]);

        $node = Mockery::mock(BitcoinNode::class);
        $node->shouldReceive('api')->andReturn($mockApi);

        $result = $this->bitcoin->validateAddress($node, '3J98t1WpEZ73CNmYviecrnyiWrnqRhWNLy');

        $this->assertEquals(AddressType::P2SH_SEGWIT, $result);
    }

    public function test_validate_address_returns_legacy_by_default()
    {
        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->once()
            ->with('validateaddress', ['address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa'])
            ->andReturn([
                'isvalid' => true,
                'iswitness' => false,
                'isscript' => false
            ]);

        $node = Mockery::mock(BitcoinNode::class);
        $node->shouldReceive('api')->andReturn($mockApi);

        $result = $this->bitcoin->validateAddress($node, '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa');

        $this->assertEquals(AddressType::LEGACY, $result);
    }

    public function test_send_all_throws_exception_when_incomplete()
    {
        // Create real models from database
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
            'password' => 'pass',
            'descriptors' => [['desc' => 'test']],
        ]);

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->with('walletpassphrase', Mockery::any(), 'testwallet')
            ->andReturn(['result' => true]);

        $mockApi->shouldReceive('request')
            ->with('sendall', Mockery::any(), 'testwallet')
            ->andReturn(['complete' => false]);

        // Mock the node's api method
        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $this->expectException(\Exception::class);

        $this->bitcoin->sendAll($wallet, 'bc1qaddress', null);
    }

    public function test_send_all_returns_txid_on_success()
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
            'password' => 'pass',
            'descriptors' => [['desc' => 'test']],
        ]);

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->with('walletpassphrase', Mockery::any(), 'testwallet')
            ->andReturn(['result' => true]);

        $mockApi->shouldReceive('request')
            ->with('sendall', Mockery::any(), 'testwallet')
            ->andReturn([
                'complete' => true,
                'txid' => 'abc123txid'
            ]);

        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $txid = $this->bitcoin->sendAll($wallet, 'bc1qaddress', null);

        $this->assertEquals('abc123txid', $txid);
    }

    public function test_send_converts_decimal_amount_correctly()
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
            'password' => 'pass',
            'descriptors' => [['desc' => 'test']],
        ]);

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->with('walletpassphrase', Mockery::any(), 'testwallet')
            ->andReturn(['result' => true]);

        $mockApi->shouldReceive('request')
            ->with('sendtoaddress', Mockery::any(), 'testwallet')
            ->andReturn(['result' => 'txid123']);

        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $amount = new Decimal('0.5', 8);
        $txid = $this->bitcoin->send($wallet, 'bc1qaddress', $amount, null, false);

        $this->assertEquals('txid123', $txid);
    }

    public function test_send_with_custom_fee_rate()
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
            'password' => 'pass',
            'descriptors' => [['desc' => 'test']],
        ]);

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->with('walletpassphrase', Mockery::any(), 'testwallet')
            ->andReturn(['result' => true]);

        $mockApi->shouldReceive('request')
            ->with('sendtoaddress', Mockery::any(), 'testwallet')
            ->andReturn(['result' => 'txid456']);

        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $amount = new Decimal('1.0', 8);
        $txid = $this->bitcoin->send($wallet, 'bc1qaddress', $amount, 50, false);

        $this->assertEquals('txid456', $txid);
    }

    public function test_send_throws_exception_on_failure()
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
            'password' => 'pass',
            'descriptors' => [['desc' => 'test']],
        ]);

        $mockApi = Mockery::mock(BitcoindRpcApi::class);
        $mockApi->shouldReceive('request')
            ->with('walletpassphrase', Mockery::any(), 'testwallet')
            ->andReturn(['result' => true]);

        $mockApi->shouldReceive('request')
            ->with('sendtoaddress', Mockery::any(), 'testwallet')
            ->andReturn(['result' => null, 'error' => 'Insufficient funds']);

        $wallet->node = Mockery::mock($wallet->node)->makePartial();
        $wallet->node->shouldReceive('api')->andReturn($mockApi);

        $this->expectException(\Exception::class);

        $amount = new Decimal('1.0', 8);
        $this->bitcoin->send($wallet, 'bc1qaddress', $amount, null, false);
    }
}
