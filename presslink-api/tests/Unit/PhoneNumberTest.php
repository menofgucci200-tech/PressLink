<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function typedNumbers(): array
    {
        return [
            'saisie groupée' => ['07 08 12 44 00', '+2250708124400'],
            'saisie collée' => ['0708124400', '+2250708124400'],
            'avec indicatif' => ['+225 07 08 12 44 00', '+2250708124400'],
            'avec 00225' => ['00225 0708124400', '+2250708124400'],
            'avec points et tirets' => ['05.06-07.08.09', '+2250506070809'],
            'déjà au bon format' => ['+2250708124400', '+2250708124400'],
        ];
    }

    #[DataProvider('typedNumbers')]
    public function test_typed_numbers_are_put_in_the_single_format(string $typed, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($typed));
        $this->assertTrue(PhoneNumber::isValid(PhoneNumber::normalize($typed)));
    }

    /** @return array<string, array{string}> */
    public static function invalidNumbers(): array
    {
        return [
            'moins de 10 chiffres' => ['070812440'],
            'moins de 10 chiffres avec indicatif' => ['+2250701020'],
            'plus de 10 chiffres' => ['07081244001'],
            'ancien format à 8 chiffres' => ['08124400'],
            'autre pays' => ['+255712345678'],
            'préfixe inexistant' => ['0308124400'],
            'vide' => [''],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_invalid_numbers_are_refused(string $typed): void
    {
        $this->assertFalse(PhoneNumber::isValid(PhoneNumber::normalize($typed)));
    }

    public function test_customers_need_a_mobile_but_a_pressing_can_give_a_landline(): void
    {
        $this->assertFalse(PhoneNumber::isValid('+2252722445566'));
        $this->assertTrue(PhoneNumber::isValid('+2252722445566', mobileOnly: false));
    }

    public function test_demo_accounts_keep_their_reserved_numbers(): void
    {
        $this->assertTrue(PhoneNumber::isDemo('+2250000000001'));
        $this->assertTrue(PhoneNumber::isValid('+2250000000001'));
        $this->assertFalse(PhoneNumber::isValid('+2250000000100'));
    }

    public function test_numbers_are_displayed_by_pairs(): void
    {
        $this->assertSame('+225 07 08 12 44 00', PhoneNumber::format('+2250708124400'));
        $this->assertSame('0708124400', PhoneNumber::national('+2250708124400'));
    }
}
