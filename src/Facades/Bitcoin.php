<?php

namespace sakoora0x\LaravelBitcoinModule\Facades;

use Illuminate\Support\Facades\Facade;

class Bitcoin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \sakoora0x\LaravelBitcoinModule\Bitcoin::class;
    }
}
