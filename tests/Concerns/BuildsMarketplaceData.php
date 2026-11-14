<?php

namespace Tests\Concerns;

use App\Enums\AccountType;
use App\Enums\Direction;
use App\Models\LedgerAccount;
use App\Models\Listing;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\UploadedFile;

trait BuildsMarketplaceData
{
    protected function makeAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin->fresh();
    }

    protected function makeListing(User $seller, array $overrides = []): Listing
    {
        $listing = new Listing(array_merge([
            'title' => 'iPhone 13 Pro',
            'description' => 'Barely used, comes with the box and charger.',
            'category' => 'phones',
            'condition' => 'used',
            'listing_type' => 'single',
            'price' => 450_000_00,
            'location' => 'Lagos',
            'offers_shipping' => true,
            'leverage_fee_supported' => true,
            'images' => ['listings/a.jpg', 'listings/b.jpg', 'listings/c.jpg'],
        ], array_diff_key($overrides, ['status' => 1])));
        $listing->seller_id = $seller->id;
        $listing->status = $overrides['status'] ?? 'active';
        $listing->save();

        return $listing;
    }

    protected function giveWallet(User $user, int $kobo): void
    {
        app(LedgerService::class)->post('deposit', 'test-seed-'.$user->id.'-'.uniqid(), [
            ['account' => LedgerAccount::system(AccountType::GatewayClearing), 'direction' => Direction::Debit, 'amount' => $kobo],
            ['account' => LedgerAccount::forUser($user), 'direction' => Direction::Credit, 'amount' => $kobo],
        ]);
    }

    protected function wallet(User $user): int
    {
        return LedgerAccount::forUser($user)->fresh()->balance;
    }

    /** A real 1x1 PNG, so validation sees a genuine image without needing the GD extension. */
    protected function fakePhoto(string $name = 'photo.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
