<?php

namespace App\Models;

use App\Enums\EscrowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escrow extends Model
{
    // Money and status fields are never mass-assignable; only EscrowService sets them.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => EscrowStatus::class,
            'amount' => 'integer',
            'fee' => 'integer',
            'fee_bps' => 'integer',
            'funded_at' => 'datetime',
            'delivered_at' => 'datetime',
            'auto_release_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function sellerPayout(): int
    {
        return $this->amount - $this->fee;
    }
}
