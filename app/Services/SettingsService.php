<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Admin-editable configuration. Secrets (API keys) are encrypted at rest with the app key
 * and never returned by all(); callers must ask for a secret by name.
 */
class SettingsService
{
    public const DEFAULTS = [
        'escrow.fee_bps' => 200,              // 2.00 percent
        'escrow.auto_release_days' => 7,
        'escrow.min_amount_kobo' => 100_00,   // NGN 100
        'escrow.max_amount_kobo' => 5_000_000_00, // NGN 5,000,000
        'wallet.min_deposit_kobo' => 100_00,
        'paystack.public_key' => null,
        'paystack.secret_key' => null,
    ];

    public const SECRETS = ['paystack.secret_key'];

    public function get(string $key, mixed $default = null): mixed
    {
        $row = Cache::rememberForever($this->cacheKey($key), fn () => Setting::where('key', $key)->first()?->only(['value', 'is_secret']) ?? false);

        if ($row === false || $row['value'] === null) {
            return $default ?? (self::DEFAULTS[$key] ?? null);
        }

        $value = $row['is_secret'] ? Crypt::decryptString($row['value']) : $row['value'];

        return $this->cast($key, $value);
    }

    public function set(string $key, mixed $value): void
    {
        $secret = in_array($key, self::SECRETS, true);
        $stored = $value === null ? null : ($secret ? Crypt::encryptString((string) $value) : (string) $value);

        Setting::updateOrCreate(['key' => $key], ['value' => $stored, 'is_secret' => $secret]);
        Cache::forget($this->cacheKey($key));
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    private function cast(string $key, string $value): mixed
    {
        return is_int(self::DEFAULTS[$key] ?? null) ? (int) $value : $value;
    }

    private function cacheKey(string $key): string
    {
        return 'settings:'.$key;
    }
}
