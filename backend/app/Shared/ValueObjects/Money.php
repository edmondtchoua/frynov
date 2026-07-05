<?php

namespace App\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object immuable pour les montants monétaires.
 * Toujours stocké en centimes (entier) pour éviter les erreurs float.
 */
final class Money
{
    public function __construct(
        private readonly int    $amount,   // En centimes : 1500 = 15.00
        private readonly string $currency, // ISO 4217 : XOF, NGN, USD, MAD
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException("Le montant ne peut pas être négatif : {$amount}");
        }
    }

    public static function of(int $amountInCents, string $currency): self
    {
        return new self($amountInCents, strtoupper($currency));
    }

    public static function zero(string $currency = 'XOF'): self
    {
        return new self(0, $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self(max(0, $this->amount - $other->amount), $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function format(): string
    {
        return number_format($this->amount / 100, 2) . ' ' . $this->currency;
    }

    public function toArray(): array
    {
        return [
            'amount'   => $this->amount,
            'currency' => $this->currency,
        ];
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Devises incompatibles : {$this->currency} vs {$other->currency}"
            );
        }
    }
}
