<?php

namespace Decimal;

/**
 * Polyfill for Decimal class when ext-decimal is not available
 * This is only for testing purposes
 */
if (!class_exists('Decimal\Decimal')) {
    class Decimal
    {
        private string $value;
        private int $precision;

        public function __construct(string|int|float $value, int $precision = 28)
        {
            $this->value = (string) $value;
            $this->precision = $precision;
        }

        public function toString(): string
        {
            return $this->value;
        }

        public function __toString(): string
        {
            return $this->toString();
        }

        public function add(Decimal|string|int|float $value): Decimal
        {
            $val = $value instanceof Decimal ? $value->value : (string) $value;
            return new Decimal((string) (bcadd($this->value, $val, 8)), $this->precision);
        }

        public function sub(Decimal|string|int|float $value): Decimal
        {
            $val = $value instanceof Decimal ? $value->value : (string) $value;
            return new Decimal((string) (bcsub($this->value, $val, 8)), $this->precision);
        }

        public function mul(Decimal|string|int|float $value): Decimal
        {
            $val = $value instanceof Decimal ? $value->value : (string) $value;
            return new Decimal((string) (bcmul($this->value, $val, 8)), $this->precision);
        }

        public function div(Decimal|string|int|float $value): Decimal
        {
            $val = $value instanceof Decimal ? $value->value : (string) $value;
            return new Decimal((string) (bcdiv($this->value, $val, 8)), $this->precision);
        }

        public function equals(Decimal|string|int|float $value): bool
        {
            $val = $value instanceof Decimal ? $value->value : (string) $value;
            return bccomp($this->value, $val, 8) === 0;
        }

        public function toFixed(?int $places = null): string
        {
            $places = $places ?? $this->precision;
            return number_format((float) $this->value, $places, '.', '');
        }
    }
}
