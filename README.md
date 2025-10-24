![Pest Laravel Expectations](https://banners.beyondco.de/Bitcoin.png?theme=light&packageManager=composer+require&packageName=mollsoft%2Flaravel-bitcoin-module&pattern=architect&style=style_1&description=Working+with+cryptocurrency+Bitcoin&md=1&showWatermark=1&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)

<a href="https://packagist.org/packages/sakoora0x/laravel-bitcoin-module" target="_blank">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/packagist/v/sakoora0x/laravel-bitcoin-module.svg?style=flat&cacheSeconds=3600" alt="Latest Version on Packagist">
</a>

<a href="https://www.php.net">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/badge/php-%3E=8.2-brightgreen.svg?maxAge=2592000" alt="Php Version">
</a>

<a href="https://laravel.com/">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/badge/laravel-11%20%7C%2012-red.svg?maxAge=2592000" alt="Laravel Version">
</a>

<a href="https://packagist.org/packages/sakoora0x/laravel-bitcoin-module" target="_blank">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/packagist/dt/sakoora0x/laravel-bitcoin-module.svg?style=flat&cacheSeconds=3600" alt="Total Downloads">
</a>

<a href="https://mollsoft.com"><img alt="Website" src="https://img.shields.io/badge/Website-https://mollsoft.com-black"></a>
<a href="https://t.me/mollsoft"><img alt="Telegram" src="https://img.shields.io/badge/Telegram-@mollsoft-blue"></a>

---

**Laravel Bitcoin Module** is a Laravel package for work with cryptocurrency Bitcoin. You can create descriptor wallets, generate addresses, track current balances, collect transaction history, organize payment acceptance on your website, and automate outgoing transfers.

You can contact me for help in integrating payment acceptance into your project.

## Table of Contents

- [Features](#features)
- [Examples](#examples)
- [Installation](#installation)
- [Commands](#commands)
- [WebHook](#webhook)
- [Testing](#testing)
- [Requirements](#requirements)
- [Configuring RPC Authentication](#configuring-rpc-authentication)

## Features

- ✅ **Multi-Node Support** - Manage multiple Bitcoin nodes
- ✅ **Descriptor Wallets** - Create and import descriptor-based wallets
- ✅ **Address Generation** - Support for all address types (Legacy, P2SH-SegWit, Bech32, Bech32m)
- ✅ **Transaction Management** - Send BTC, send all, custom fee rates
- ✅ **Automated Syncing** - Background wallet synchronization
- ✅ **Deposit Tracking** - Automatic incoming transaction detection
- ✅ **Webhook Support** - Get notified of new deposits
- ✅ **Encrypted Storage** - Secure wallet passwords and descriptors
- ✅ **Precise Decimals** - Satoshi-level precision (8 decimal places)
- ✅ **Laravel 11 & 12** - Full support for latest Laravel versions
- ✅ **Comprehensive Tests** - 88 tests with 100% pass rate

## Examples

Create Descriptor Wallet:
```php
$name = 'my-wallet';
$password = 'password for encrypt wallet files';
$title = 'My First Wallet';

$node = Bitcoin::createNode('localhost', 'LocalHost', '127.0.0.1');
$wallet = Bitcoin::createWallet($node, $name, $password, $title);
```

Import Descriptor Wallet using descriptors:
```php
$name = 'my-wallet';
$password = 'password for encrypt wallet files';
$descriptions = json_decode('DESCRIPTORS JSON', true);
$title = 'My First Wallet';

$node = Bitcoin::createNode('localhost', 'LocalHost', '127.0.0.1');
$wallet = Bitcoin::importWallet($node, $name, $descriptions, $password, $title);
```

Create address:
```php
$wallet = BitcoinWallet::firstOrFail();
$title = 'My address title';

$address = Bitcoin::createAddress($wallet, AddressType::BECH32, $title);
```

Validate address:
```php
$address = '....';

$node = BitcoinNode::firstOrFail();
$addressType = Bitcoin::validateAddress($node, $address);
if( $addressType === null ) {
    die('Address is not valid!');
} 

var_dump($addressType); // Enum value of AddressType
```

Send all BTC from wallet:
```php
$wallet = BitcoinWallet::firstOrFail();
$address = 'to_address';

$txid = Bitcoin::sendAll($wallet, $address);

echo 'TXID: '.$txid;
```

Send BTC from wallet:
```php
$wallet = BitcoinWallet::firstOrFail();
$address = 'to_address';
$amount = 0.001;

$txid = Bitcoin::send($wallet, $address, $amount);

echo 'TXID: '.$txid;
```


### Installation
You can install the package via composer:
```bash
composer require sakoora0x/laravel-bitcoin-module
```

After you can run installer using command:
```bash
php artisan bitcoin:install
```

And run migrations:
```bash
php artisan migrate
```

The package will automatically register its service provider and facade via Laravel's package auto-discovery.

### Laravel 11+ Task Scheduling

In Laravel 11+, add the following to your `routes/console.php` file:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('bitcoin:sync')
    ->everyMinute()
    ->runInBackground();
```

### Laravel 10 Task Scheduling

For Laravel 10, add to `app/Console/Kernel.php` in the `schedule()` method:

```php
$schedule->command('bitcoin:sync')
    ->everyMinute()
    ->runInBackground();
```

## Commands

Scan transactions and update balances:

```bash
> php artisan bitcoin:sync
```

Scan transactions and update balances for wallet:

```bash
> php artisan bitcoin:sync-wallet {wallet_id}
```

## WebHook

You can set up a WebHook that will be called when a new incoming BTC deposit is detected.

In file config/bitcoin.php you can set param:

```php
'webhook_handler' => \sakoora0x\LaravelBitcoinModule\WebhookHandlers\EmptyWebhookHandler::class,
```

Example WebHook handler:

```php
class EmptyWebhookHandler implements WebhookHandlerInterface
{
    public function handle(BitcoinWallet $wallet, BitcoinAddress $address, BitcoinDeposit $transaction): void
    {
        Log::error('Bitcoin Wallet '.$wallet->name.' new transaction '.$transaction->txid.' for address '.$address->address);
    }
}
```

## Testing

This package includes a comprehensive test suite with 88 tests covering all major components:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage
```

The test suite includes:
- Unit tests for core functionality (RPC API, address validation, decimal casting)
- Feature tests for models, commands, and integration workflows
- 100% pass rate with 192 assertions

For more details, see [TEST_STATUS.md](TEST_STATUS.md).

## Requirements

The following versions are supported by this package:

* PHP 8.2 or newer
* Laravel 11.x or 12.x
* PHP Extensions: BCMath (usually enabled by default)
* PHP Extensions: Decimal (optional, for better performance with decimal calculations)

### Configuring RPC Authentication

The `rpcauth` line in your `.bitcoin/bitcoin.conf` file contains authentication credentials for connecting to your Bitcoin node. To create a different login, you need to generate a new `rpcauth` string.

#### Using the rpcauth.py script (Recommended)

This package includes a Python script to generate these credentials:

1. Generate new credentials (replace `newusername` with your desired username):
```bash
python3 rpcauth.py newusername
```

This will output something like:
```
String to be appended to bitcoin.conf:
rpcauth=newusername:salt$hash
Your password:
randomGeneratedPassword123
```

3. Copy the `rpcauth` line to your config file and save the password - you'll need it to connect.

#### Manual generation

If you want to generate it manually, the format is:
```
rpcauth=username:salt$hmac_sha256(salt, password)