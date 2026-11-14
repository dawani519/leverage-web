<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => AccountType::class, 'balance' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** Per-user accounts are safe to firstOrCreate: (type, user_id) is a real unique key. */
    public static function forUser(User $user, AccountType $type = AccountType::UserWallet): self
    {
        return static::firstOrCreate(
            ['type' => $type->value, 'user_id' => $user->id],
            ['currency' => 'NGN', 'balance' => 0],
        );
    }

    public static function system(AccountType $type, int $shard = 0): self
    {
        return static::where('type', $type->value)->whereNull('user_id')->where('shard', $shard)->firstOrFail();
    }

    /** Deterministic shard for a key (e.g. an escrow uuid or deposit reference). */
    public static function shardFor(string|int $key): int
    {
        return crc32((string) $key) % max(1, (int) config('ledger.shards'));
    }

    /** The system account for a key, spreading lock contention across shards. */
    public static function systemFor(AccountType $type, string|int $key): self
    {
        return static::system($type, static::shardFor($key));
    }

    /** Total across every shard of a system account type. */
    public static function totalBalance(AccountType $type): int
    {
        return (int) static::where('type', $type->value)->whereNull('user_id')->sum('balance');
    }
}
