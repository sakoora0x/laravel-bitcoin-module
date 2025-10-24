<?php

namespace sakoora0x\LaravelBitcoinModule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use sakoora0x\LaravelBitcoinModule\Enums\AddressType;

class AddressTypeTest extends TestCase
{
    public function test_legacy_has_correct_value()
    {
        $this->assertEquals('legacy', AddressType::LEGACY->value);
    }

    public function test_p2sh_segwit_has_correct_value()
    {
        $this->assertEquals('p2wpkh-p2sh', AddressType::P2SH_SEGWIT->value);
    }

    public function test_bech32_has_correct_value()
    {
        $this->assertEquals('bech32', AddressType::BECH32->value);
    }

    public function test_bech32m_has_correct_value()
    {
        $this->assertEquals('bech32m', AddressType::BECH32M->value);
    }

    public function test_values_returns_all_enum_values()
    {
        $values = AddressType::values();

        $this->assertIsArray($values);
        $this->assertCount(4, $values);
        $this->assertContains('legacy', $values);
        $this->assertContains('p2wpkh-p2sh', $values);
        $this->assertContains('bech32', $values);
        $this->assertContains('bech32m', $values);
    }

    public function test_can_create_from_value()
    {
        $type = AddressType::from('legacy');
        $this->assertEquals(AddressType::LEGACY, $type);

        $type = AddressType::from('p2wpkh-p2sh');
        $this->assertEquals(AddressType::P2SH_SEGWIT, $type);

        $type = AddressType::from('bech32');
        $this->assertEquals(AddressType::BECH32, $type);

        $type = AddressType::from('bech32m');
        $this->assertEquals(AddressType::BECH32M, $type);
    }

    public function test_try_from_returns_null_for_invalid_value()
    {
        $type = AddressType::tryFrom('invalid');
        $this->assertNull($type);
    }

    public function test_all_cases_are_present()
    {
        $cases = AddressType::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(AddressType::LEGACY, $cases);
        $this->assertContains(AddressType::P2SH_SEGWIT, $cases);
        $this->assertContains(AddressType::BECH32, $cases);
        $this->assertContains(AddressType::BECH32M, $cases);
    }

    public function test_enum_values_match_bitcoin_core_address_types()
    {
        // These values should match what Bitcoin Core expects
        $this->assertEquals('legacy', AddressType::LEGACY->value);
        $this->assertEquals('p2wpkh-p2sh', AddressType::P2SH_SEGWIT->value);
        $this->assertEquals('bech32', AddressType::BECH32->value);
        $this->assertEquals('bech32m', AddressType::BECH32M->value);
    }
}
