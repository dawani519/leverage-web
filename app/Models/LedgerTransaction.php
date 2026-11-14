<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // Ledger history is immutable. Corrections are new reversing transactions.
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ledger transactions are immutable.'));
        static::deleting(fn () => throw new \LogicException('Ledger transactions are immutable.'));
    }
}
