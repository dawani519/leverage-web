<?php

namespace App\Enums;

enum AccountType: string
{
    case UserWallet = 'user_wallet';
    case EscrowHolding = 'escrow_holding';
    case PlatformRevenue = 'platform_revenue';
    case GatewayClearing = 'gateway_clearing';

    /** Asset accounts grow on debit; liability and revenue accounts grow on credit. */
    public function isDebitNormal(): bool
    {
        return $this === self::GatewayClearing;
    }

    /** Accounts holding customer money may never go below zero. */
    public function forbidsOverdraft(): bool
    {
        return in_array($this, [self::UserWallet, self::EscrowHolding], true);
    }
}
