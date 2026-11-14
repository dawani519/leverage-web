<?php

namespace App\Enums;

enum EscrowStatus: string
{
    case PendingPayment = 'pending_payment';
    case Funded = 'funded';
    case Delivered = 'delivered';
    case Disputed = 'disputed';
    case Released = 'released';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Funded, self::Cancelled],
            self::Funded => [self::Delivered, self::Disputed, self::Released, self::Refunded],
            self::Delivered => [self::Disputed, self::Released, self::Refunded],
            self::Disputed => [self::Released, self::Refunded],
            self::Released, self::Refunded, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedNext() === [];
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Funded => 'Paid, awaiting delivery',
            self::Delivered => 'Delivered, awaiting confirmation',
            self::Disputed => 'In dispute',
            self::Released => 'Completed',
            self::Refunded => 'Refunded',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Flux badge colour. */
    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'zinc',
            self::Funded => 'blue',
            self::Delivered => 'amber',
            self::Disputed => 'red',
            self::Released => 'green',
            self::Refunded => 'purple',
            self::Cancelled => 'zinc',
        };
    }

    /** Funds are currently sitting in escrow. */
    public function holdsFunds(): bool
    {
        return in_array($this, [self::Funded, self::Delivered, self::Disputed], true);
    }
}
