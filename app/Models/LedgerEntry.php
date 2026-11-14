<?php

namespace App\Models;

use App\Enums\Direction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['direction' => Direction::class, 'amount' => 'integer', 'balance_after' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new \LogicException('Ledger entries are immutable.'));
    }
}
