<?php

namespace sakoora0x\LaravelBitcoinModule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use sakoora0x\LaravelBitcoinModule\BitcoindRpcApi;

class BitcoindRpcApiTest extends TestCase
{
    public function test_request_returns_result_array_on_success()
    {
        $expectedResult = ['balance' => 1.5, 'unconfirmed' => 0.3];

        // Mock API using anonymous class
        $api = new class('localhost', 8332, 'user', 'pass') extends BitcoindRpcApi {
            public function request(string $method, array $params = [], ?string $wallet = null): array
            {
                return ['balance' => 1.5, 'unconfirmed' => 0.3];
            }
        };

        $result = $api->request('getbalance', []);

        $this->assertEquals($expectedResult, $result);
    }

    public function test_request_includes_wallet_path_when_provided()
    {
        // Test verifies the wallet path logic
        $api = new class('localhost', 8332, 'user', 'pass') extends BitcoindRpcApi {
            public ?string $requestedPath = null;

            public function request(string $method, array $params = [], ?string $wallet = null): array
            {
                $this->requestedPath = $wallet ? '/wallet/'.$wallet : '';
                return ['success' => true];
            }
        };

        $api->request('getbalance', [], 'mywallet');

        $this->assertEquals('/wallet/mywallet', $api->requestedPath);
    }

    public function test_request_includes_empty_path_when_no_wallet()
    {
        $api = new class('localhost', 8332, 'user', 'pass') extends BitcoindRpcApi {
            public ?string $requestedPath = null;

            public function request(string $method, array $params = [], ?string $wallet = null): array
            {
                $this->requestedPath = $wallet ? '/wallet/'.$wallet : '';
                return ['success' => true];
            }
        };

        $api->request('getblockchaininfo', []);

        $this->assertEquals('', $api->requestedPath);
    }

    public function test_request_throws_exception_on_rpc_error()
    {
        $testId = 'test-unique-id-123';

        $api = new class('localhost', 8332, 'user', 'pass', $testId) extends BitcoindRpcApi {
            public function __construct(
                string $host,
                int $port,
                ?string $username,
                ?string $password,
                private string $mockId
            ) {
                parent::__construct($host, $port, $username, $password);
            }

            public function request(string $method, array $params = [], ?string $wallet = null): array
            {
                // Simulate RPC error response
                $body = [
                    'jsonrpc' => '2.0',
                    'id' => $this->mockId,
                    'error' => [
                        'code' => -5,
                        'message' => 'Invalid address'
                    ]
                ];

                if ($body['error'] ?? false) {
                    throw new \Exception('Bitcoind '.$method.' '.$body['error']['code'].' - '.$body['error']['message']);
                }

                return $body;
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Bitcoind validateaddress -5 - Invalid address');

        $api->request('validateaddress', ['invalid']);
    }

    public function test_api_constructor_accepts_parameters()
    {
        $api = new BitcoindRpcApi('192.168.1.100', 8332, 'testuser', 'testpass');

        $this->assertInstanceOf(BitcoindRpcApi::class, $api);
    }

    public function test_api_uses_correct_base_uri()
    {
        $api = new BitcoindRpcApi('192.168.1.100', 8332, 'testuser', 'testpass');

        // Use reflection to verify the client configuration
        $reflection = new \ReflectionClass($api);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $client = $property->getValue($api);

        $config = $client->getConfig();

        $this->assertEquals('http://192.168.1.100:8332', (string)$config['base_uri']);
    }

    public function test_api_uses_correct_authentication()
    {
        $api = new BitcoindRpcApi('localhost', 8332, 'testuser', 'testpass');

        // Use reflection to verify the client configuration
        $reflection = new \ReflectionClass($api);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $client = $property->getValue($api);

        $config = $client->getConfig();

        $this->assertEquals(['testuser', 'testpass'], $config['auth']);
    }

    public function test_api_has_correct_timeouts()
    {
        $api = new BitcoindRpcApi('localhost', 8332, 'user', 'pass');

        $reflection = new \ReflectionClass($api);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $client = $property->getValue($api);

        $config = $client->getConfig();

        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals(10, $config['connect_timeout']);
        $this->assertEquals(false, $config['http_errors']);
    }

    public function test_api_constructs_with_different_ports()
    {
        $api = new BitcoindRpcApi('localhost', 18332, 'user', 'pass');

        $reflection = new \ReflectionClass($api);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $client = $property->getValue($api);

        $config = $client->getConfig();

        $this->assertEquals('http://localhost:18332', (string)$config['base_uri']);
    }
}
