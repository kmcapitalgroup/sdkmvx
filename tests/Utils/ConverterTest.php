<?php

namespace KmcpG\MultiversxSdkLaravel\Tests\Utils;

use Orchestra\Testbench\TestCase; // Use Testbench TestCase if needed, or PHPUnit directly
use KmcpG\MultiversxSdkLaravel\Utils\Converter;
use KmcpG\MultiversxSdkLaravel\Services\WalletService;
use KmcpG\MultiversxSdkLaravel\MultiversxServiceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class ConverterTest extends TestCase
{
    // Required if extending Testbench TestCase to load providers
    protected function getPackageProviders($app)
    {
        return [MultiversxServiceProvider::class];
    }

    //--- egldToAtomic Tests ---
    #[Test]
    public function it_converts_egld_to_atomic_units()
    {
        $this->assertEquals('1000000000000000000', Converter::egldToAtomic(1));
        $this->assertEquals('1500000000000000000', Converter::egldToAtomic(1.5));
        $this->assertEquals('500000000000000000', Converter::egldToAtomic(0.5));
        $this->assertEquals('123450000000000000000', Converter::egldToAtomic('123.45'));
        $this->assertEquals('0', Converter::egldToAtomic(0));
        $this->assertEquals('0', Converter::egldToAtomic('0.0'));
        $this->assertEquals('9999999999000000000000000000', Converter::egldToAtomic('9999999999')); // Large int
    }

    #[Test]
    public function it_throws_exception_for_negative_egld_amount()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid EGLD amount provided.");
        Converter::egldToAtomic(-1);
    }

    #[Test]
    public function it_throws_exception_for_non_numeric_egld_amount()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid EGLD amount provided.");
        Converter::egldToAtomic('abc');
    }

    //--- esdtToAtomic Tests ---
    #[Test]
    public function it_converts_esdt_to_atomic_units()
    {
        $this->assertEquals('123456000', Converter::esdtToAtomic('123.456', 6)); // 6 decimals
        $this->assertEquals('12300', Converter::esdtToAtomic('123', 2));       // 2 decimals
        $this->assertEquals('123', Converter::esdtToAtomic('123', 0));          // 0 decimals
        $this->assertEquals('123450000000000000000', Converter::esdtToAtomic('123.45', 18)); // 18 decimals
        $this->assertEquals('0', Converter::esdtToAtomic(0, 6));
        $this->assertEquals('0', Converter::esdtToAtomic('0.0', 18));
    }

    #[Test]
    public function it_throws_exception_for_negative_esdt_amount()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid ESDT amount provided.");
        Converter::esdtToAtomic(-10, 6);
    }

    #[Test]
    public function it_throws_exception_for_negative_esdt_decimals()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Decimals cannot be negative.");
        Converter::esdtToAtomic(10, -1);
    }

    #[Test]
    public function it_throws_exception_for_non_numeric_esdt_amount()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid ESDT amount provided.");
        Converter::esdtToAtomic('xyz', 6);
    }

    //--- encodeSmartContractArgs Tests ---
    #[Test]
    public function it_encodes_various_argument_types()
    {
        // Need WalletService to generate a valid address
        /** @var WalletService $walletService */
        $walletService = $this->app->make(WalletService::class);
        $wallet = $walletService->createWallet();
        $address = $wallet->address;
        $addressHex = WalletService::bech32ToPublicKeyHex($address);

        $args = [
            gmp_init(255),       // GMP int -> ff
            10,                  // PHP int -> 0a
            'hello',             // string -> 68656c6c6f
            true,                // bool true -> 01
            false,               // bool false -> 00
            $address,            // bech32 address -> pubkey hex
            gmp_init('12345678901234567890', 10) // Large GMP
        ];

        $expected = [
            'ff',
            'a', // No padding
            bin2hex('hello'),
            '01',
            '00',
            $addressHex,
            gmp_strval(gmp_init('12345678901234567890'), 16)
        ];

        $encoded = Converter::encodeSmartContractArgs($args);
        $this->assertEquals($expected, $encoded);
    }

    #[Test]
    public function it_handles_hex_padding_for_odd_length_hex()
    {
        // This test is now obsolete as we removed padding
        // We can rename it or adjust assertion if specific non-padded behavior is needed
        $args = [gmp_init(10), 15]; // 10 -> a, 15 -> f
        $expected = ['a', 'f']; // Raw hex
        $encoded = Converter::encodeSmartContractArgs($args);
        $this->assertEquals($expected, $encoded, "Hex values should not be padded.");
    }

    #[Test]
    public function it_throws_exception_for_invalid_bech32_address_argument()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid address format for conversion");
        WalletService::bech32ToPublicKeyHex('definitely_not_an_address');
    }

    #[Test]
    public function it_throws_exception_for_unsupported_argument_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported argument type encountered: array");
        Converter::encodeSmartContractArgs([[1, 2]]); // Array is not supported
    }

    //--- buildContractDataField Tests ---
    #[Test]
    public function it_builds_data_field_correctly()
    {
        $func = 'myFunction';
        $argsHex = ['01', '68656c6c6f', 'deadbeef'];
        $expected = 'myFunction@01@68656c6c6f@deadbeef';
        $this->assertEquals($expected, Converter::buildContractDataField($func, $argsHex));
    }

    #[Test]
    public function it_builds_data_field_with_no_args()
    {
        $func = 'doNothing';
        $argsHex = [];
        $expected = 'doNothing';
        $this->assertEquals($expected, Converter::buildContractDataField($func, $argsHex));
    }

    #[Test]
    public function it_throws_exception_for_empty_function_name_in_build()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function name cannot be empty when building data field.');
        Converter::buildContractDataField('', ['01']);
    }

    #[Test]
    public function it_throws_exception_for_non_hex_args_in_build()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Encoded arguments must be hexadecimal strings.');
        Converter::buildContractDataField('myFunc', ['01', 'notHex']);
    }
} 