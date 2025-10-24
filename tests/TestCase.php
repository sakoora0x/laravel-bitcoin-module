<?php

namespace sakoora0x\LaravelBitcoinModule\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use sakoora0x\LaravelBitcoinModule\BitcoinServiceProvider;

// Load Decimal polyfill if ext-decimal is not available
if (!extension_loaded('decimal')) {
    require_once __DIR__ . '/Helpers/DecimalPolyfill.php';
}

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'sakoora0x\\LaravelBitcoinModule\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            BitcoinServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set encryption key for testing
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Run migrations in correct order
        $migration = include __DIR__.'/../database/migrations/create_bitcoin_nodes_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_bitcoin_wallets_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_bitcoin_addresses_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_bitcoin_deposits_table.php.stub';
        $migration->up();
    }
}
