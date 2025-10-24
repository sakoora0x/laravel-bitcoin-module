<?php

namespace sakoora0x\LaravelBitcoinModule\Tests\Unit;

use Decimal\Decimal;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use sakoora0x\LaravelBitcoinModule\Casts\DecimalCast;

class DecimalCastTest extends TestCase
{
    private DecimalCast $cast;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new DecimalCast();
    }

    public function test_get_converts_string_to_decimal()
    {
        $model = $this->createMock(Model::class);
        $result = $this->cast->get($model, 'balance', '1.50000000', []);

        $this->assertInstanceOf(Decimal::class, $result);
        $this->assertEquals('1.50000000', $result->toString());
    }

    public function test_get_converts_null_to_zero_decimal()
    {
        $model = $this->createMock(Model::class);
        $result = $this->cast->get($model, 'balance', null, []);

        $this->assertInstanceOf(Decimal::class, $result);
        $this->assertEquals('0', $result->toString());
    }

    public function test_get_converts_zero_to_decimal()
    {
        $model = $this->createMock(Model::class);
        $result = $this->cast->get($model, 'balance', 0, []);

        $this->assertInstanceOf(Decimal::class, $result);
        $this->assertEquals('0', $result->toString());
    }

    public function test_get_handles_empty_string()
    {
        $model = $this->createMock(Model::class);
        $result = $this->cast->get($model, 'balance', '', []);

        $this->assertInstanceOf(Decimal::class, $result);
        $this->assertEquals('0', $result->toString());
    }

    public function test_get_handles_large_numbers()
    {
        $model = $this->createMock(Model::class);
        $result = $this->cast->get($model, 'balance', '21000000.00000000', []);

        $this->assertInstanceOf(Decimal::class, $result);
        $this->assertEquals('21000000.00000000', $result->toString());
    }

    public function test_set_converts_decimal_to_string()
    {
        $model = $this->createMock(Model::class);
        $decimal = new Decimal('0.12345678');

        $result = $this->cast->set($model, 'balance', $decimal, []);

        $this->assertIsString($result);
        $this->assertEquals('0.12345678', $result);
    }

    public function test_set_preserves_non_decimal_values()
    {
        $model = $this->createMock(Model::class);

        $result = $this->cast->set($model, 'balance', '1.5', []);

        $this->assertEquals('1.5', $result);
    }

    public function test_set_handles_null_values()
    {
        $model = $this->createMock(Model::class);

        $result = $this->cast->set($model, 'balance', null, []);

        $this->assertNull($result);
    }

    public function test_decimal_precision_is_maintained()
    {
        $model = $this->createMock(Model::class);

        // Test with 8 decimal places (Bitcoin precision)
        $decimal = new Decimal('0.00000001');
        $result = $this->cast->set($model, 'balance', $decimal, []);

        $this->assertEquals('0.00000001', $result);

        // Convert back
        $retrieved = $this->cast->get($model, 'balance', $result, []);
        $this->assertEquals('0.00000001', $retrieved->toString());
    }

    public function test_round_trip_conversion()
    {
        $model = $this->createMock(Model::class);
        $originalValue = '123.45678901';

        // Simulate database round trip
        $decimal = $this->cast->get($model, 'balance', $originalValue, []);
        $stored = $this->cast->set($model, 'balance', $decimal, []);
        $retrieved = $this->cast->get($model, 'balance', $stored, []);

        $this->assertEquals($originalValue, $retrieved->toString());
    }
}
