# Laravel Bitcoin Module Tests

This directory contains comprehensive tests for the Laravel Bitcoin Module package.

## Test Structure

```
tests/
├── TestCase.php           # Base test case with Orchestra Testbench setup
├── Unit/                  # Unit tests for isolated components
│   ├── BitcoindRpcApiTest.php
│   ├── BitcoinTest.php
│   ├── DecimalCastTest.php
│   └── AddressTypeTest.php
└── Feature/               # Integration and feature tests
    ├── ModelTest.php
    ├── SyncServiceTest.php
    ├── CommandTest.php
    └── BitcoinIntegrationTest.php
```

## Running Tests

### Prerequisites

1. Install test dependencies:
```bash
composer install
```

2. Ensure the PHP `ext-decimal` extension is installed for precise decimal arithmetic.

### Run All Tests

```bash
composer test
# or
vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Run only unit tests
vendor/bin/phpunit --testsuite Unit

# Run only feature tests
vendor/bin/phpunit --testsuite Feature
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/BitcoindRpcApiTest.php
```

### Run with Coverage

```bash
composer test-coverage
```

This will generate an HTML coverage report in the `coverage/` directory.

## Test Coverage

### Unit Tests

#### BitcoindRpcApiTest
- JSON-RPC request structure validation
- Response parsing and error handling
- Authentication configuration
- Timeout settings
- Request ID validation

#### BitcoinTest
- Address validation for all types (Legacy, P2SH-SegWit, Bech32, Bech32m)
- Send and sendAll transaction methods
- Decimal amount conversion
- Custom fee rate handling
- Error handling for failed transactions

#### DecimalCastTest
- String to Decimal conversion
- Null and zero value handling
- Precision maintenance (8 decimal places)
- Round-trip conversion accuracy
- Large number support

#### AddressTypeTest
- Enum value correctness
- All address types present
- Bitcoin Core compatibility
- From/tryFrom functionality

### Feature Tests

#### ModelTest
- CRUD operations for all models
- Encrypted field storage and retrieval
- Model relationships (HasMany, BelongsTo)
- Decimal casting for balance fields
- DateTime casting
- Hidden attributes in JSON serialization

#### SyncServiceTest
- Wallet unlocking with password
- Balance synchronization
- Address balance updates
- Deposit syncing and updates
- Webhook execution
- Error logging
- Full sync workflow

#### CommandTest
- `bitcoin:sync` - All wallets sync
- `bitcoin:sync-wallet` - Single wallet sync
- `bitcoin:webhook` - Manual webhook execution
- Error handling in commands
- Output messages verification

#### BitcoinIntegrationTest
- End-to-end node creation
- Complete wallet and address workflow
- Deposit tracking lifecycle
- Multiple wallets per node
- Multiple addresses per wallet
- Balance aggregation
- Precision in deposit amounts (satoshi level)
- Encrypted data verification
- Cascade queries with relationships

## Testing Approach

### Database
Tests use an in-memory SQLite database with migrations applied automatically in the `TestCase` setup. Each test runs in a transaction that is rolled back, ensuring test isolation.

### Mocking
- **BitcoindRpcApi**: Mocked to avoid requiring a real Bitcoin node
- **Guzzle HTTP Client**: Mocked for testing RPC communication
- **WebhookHandlers**: Mocked to test webhook execution without side effects
- **SyncService**: Partially mocked in command tests

### Test Data
Tests create minimal fixtures needed for each test case. Models are created using Eloquent's `create()` method with realistic Bitcoin addresses and transaction IDs.

## Key Testing Patterns

### 1. Model Relationships
```php
$node = BitcoinNode::create([...]);
$wallet = $node->wallets()->create([...]);
$address = $wallet->addresses()->create([...]);

$this->assertEquals($node->id, $wallet->node->id);
```

### 2. Encrypted Fields
```php
$node->password = 'secret';
$node->save();

// Raw DB value is encrypted
$raw = DB::table('bitcoin_nodes')->find($node->id);
$this->assertNotEquals('secret', $raw->password);

// Model accessor decrypts
$this->assertEquals('secret', $node->password);
```

### 3. Decimal Precision
```php
$deposit = Deposit::create(['amount' => '0.00000001']);
$this->assertEquals('0.00000001', $deposit->amount->toString());
```

### 4. Command Testing
```php
$this->artisan('bitcoin:sync-wallet', ['wallet_id' => $wallet->id])
    ->expectsOutput('Bitcoin Wallet testwallet starting sync...')
    ->assertSuccessful();
```

## Continuous Integration

These tests are designed to run in CI environments without external dependencies:
- No Bitcoin node required
- Uses in-memory SQLite database
- All external API calls are mocked
- Fast execution time

## Writing New Tests

When adding new features, follow these guidelines:

1. **Unit tests** for isolated logic (calculations, validations, transformations)
2. **Feature tests** for workflows involving multiple components
3. **Mock external dependencies** (Bitcoin RPC, HTTP clients, file system)
4. **Use descriptive test names** that explain what is being tested
5. **Test both success and failure paths**
6. **Verify database state** when testing persistence
7. **Test edge cases** (null values, empty arrays, large numbers)

## Debugging Tests

### Run a single test method
```bash
vendor/bin/phpunit --filter test_method_name
```

### Show output during tests
```bash
vendor/bin/phpunit --testdox
```

### Stop on first failure
```bash
vendor/bin/phpunit --stop-on-failure
```

## Dependencies

- `orchestra/testbench`: Laravel package testing environment
- `phpunit/phpunit`: Testing framework
- `mockery/mockery`: Mocking framework
- `fakerphp/faker`: Test data generation

## Known Limitations

1. Tests do not connect to a real Bitcoin node
2. Blockchain interactions are simulated via mocks
3. Cryptographic signature validation is not tested
4. Network-level Bitcoin protocol is not tested

These limitations are intentional to keep tests fast, deterministic, and runnable in any environment.
