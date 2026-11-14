<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use SoftDeletes;

    protected $guarded = ['seller_id', 'status'];

    public const CATEGORIES = [
        'electronics' => 'Electronics',
        'phones' => 'Phones & tablets',
        'vehicles' => 'Vehicles',
        'property' => 'Property',
        'fashion' => 'Fashion',
        'home' => 'Home & garden',
        'services' => 'Services',
        'other' => 'Other',
    ];

    public const CONDITIONS = ['new' => 'New', 'used' => 'Used', 'refurbished' => 'Refurbished'];

    public static function imageDisk(): string
    {
        return config('filesystems.listing_disk', 'public');
    }

    /** @return list<string> */
    public function imageUrls(): array
    {
        return array_map(fn ($path) => \Illuminate\Support\Facades\Storage::disk(static::imageDisk())->url($path), $this->images ?? []);
    }

    public function coverUrl(): ?string
    {
        return $this->imageUrls()[0] ?? null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'offers_shipping' => 'boolean',
            'leverage_fee_supported' => 'boolean',
            'images' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
