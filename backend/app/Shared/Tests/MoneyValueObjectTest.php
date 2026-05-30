<?php

namespace App\Shared\Tests;

use App\Shared\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyValueObjectTest extends TestCase
{
    #[Test]
    public function it_stores_amount_in_cents(): void
    {
        $money = Money::of(1500, 'XOF');

        $this->assertEquals(1500, $money->amount());
        $this->assertEquals('XOF', $money->currency());
    }

    #[Test]
    public function it_adds_two_amounts_correctly(): void
    {
        $a = Money::of(1000, 'XOF');
        $b = Money::of(500, 'XOF');

        $result = $a->add($b);

        $this->assertEquals(1500, $result->amount());
    }

    #[Test]
    public function it_subtracts_without_going_negative(): void
    {
        $price    = Money::of(5000, 'XOF');
        $discount = Money::of(8000, 'XOF'); // supérieur au prix

        $result = $price->subtract($discount);

        $this->assertEquals(0, $result->amount()); // plancher à 0
    }

    #[Test]
    public function it_multiplies_by_factor(): void
    {
        $unitPrice = Money::of(2000, 'XOF');
        $total     = $unitPrice->multiply(3);

        $this->assertEquals(6000, $total->amount());
    }

    #[Test]
    public function it_applies_percentage_fee(): void
    {
        $amount = Money::of(10000, 'XOF');
        $fee    = $amount->multiply(0.02); // 2% frais mobile money

        $this->assertEquals(200, $fee->amount());
    }

    #[Test]
    public function it_throws_on_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(-100, 'XOF');
    }

    #[Test]
    public function it_throws_when_adding_different_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(1000, 'XOF')->add(Money::of(500, 'NGN'));
    }

    #[Test]
    public function it_detects_zero(): void
    {
        $this->assertTrue(Money::zero('XOF')->isZero());
        $this->assertFalse(Money::of(1, 'XOF')->isZero());
    }

    #[Test]
    public function it_formats_for_display(): void
    {
        $money = Money::of(150050, 'XOF'); // 1500.50 XOF

        $this->assertStringContainsString('XOF', $money->format());
    }
}
