<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broker extends Model
{
    // Status and review fields are set only by admin actions, never by mass assignment.
    protected $guarded = ['status', 'reviewed_by', 'review_note', 'reviewed_at', 'user_id'];

    protected function casts(): array
    {
        return ['dob' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }
}
