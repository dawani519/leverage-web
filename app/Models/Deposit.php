<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    // Status and amount are only ever set by DepositService.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'gateway_payload' => 'array', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
