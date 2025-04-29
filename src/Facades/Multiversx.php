<?php

namespace KmcpG\MultiversxSdkLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use KmcpG\MultiversxSdkLaravel\Contracts\MultiversxInterface;

class Multiversx extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MultiversxInterface::class;
    }
} 