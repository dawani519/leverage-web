<?php

namespace Tests\Feature\Escrow;

use App\Enums\EscrowStatus;
use App\Exceptions\EscrowException;
use App\Models\User;
use App\Services\Escrow\EscrowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplaceData;
use Tests\TestCase;

class ListingSaleGuardTest extends TestCase
{
    use BuildsMarketplaceData, RefreshDatabase;

    private EscrowService $escrows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escrows = app(EscrowService::class);
    }

    public function test_two_buyers_cannot_both_pay_for_the_same_single_item(): void
    {
        $seller = User::factory()->create();
        [$first, $second] = [User::factory()->create(), User::factory()->create()];
        $listing = $this->makeListing($seller, ['price' => 100_000_00]);
        $this->giveWallet($first, 100_000_00);
        $this->giveWallet($second, 100_000_00);

        // Both can start (pending costs nothing), only one can pay.
        $a = $this->escrows->create($first, $seller, 100_000_00, $listing);
        $b = $this->escrows->create($second, $seller, 100_000_00, $listing);

        $this->escrows->fund($a, $first);

        try {
            $this->escrows->fund($b, $second);
            $this->fail('The same item was sold twice.');
        } catch (EscrowException $e) {
            $this->assertStringContainsString('sale in progress', $e->getMessage());
        }

        $this->assertSame(EscrowStatus::PendingPayment, $b->fresh()->status);
        $this->assertSame(100_000_00, $this->wallet($second), 'The losing buyer keeps their money.');
    }

    public function test_the_second_buyer_can_pay_if_the_first_sale_is_refunded(): void
    {
        $seller = User::factory()->create();
        [$first, $second] = [User::factory()->create(), User::factory()->create()];
        $listing = $this->makeListing($seller, ['price' => 100_000_00]);
        $this->giveWallet($first, 100_000_00);
        $this->giveWallet($second, 100_000_00);

        $a = $this->escrows->fund($this->escrows->create($first, $seller, 100_000_00, $listing), $first);
        $b = $this->escrows->create($second, $seller, 100_000_00, $listing);
        $this->escrows->refund($a, $seller);

        $this->escrows->fund($b, $second);

        $this->assertSame(EscrowStatus::Funded, $b->fresh()->status);
    }

    public function test_completing_a_sale_marks_the_listing_sold_and_blocks_later_payment(): void
    {
        $seller = User::factory()->create();
        [$first, $second] = [User::factory()->create(), User::factory()->create()];
        $listing = $this->makeListing($seller, ['price' => 100_000_00]);
        $this->giveWallet($first, 100_000_00);
        $this->giveWallet($second, 100_000_00);

        $a = $this->escrows->fund($this->escrows->create($first, $seller, 100_000_00, $listing), $first);
        $b = $this->escrows->create($second, $seller, 100_000_00, $listing);
        $this->escrows->release($a, $first);

        $this->assertSame('sold', $listing->fresh()->status);

        $this->expectException(EscrowException::class);
        $this->escrows->fund($b, $second);
    }

    public function test_a_paused_or_removed_listing_cannot_be_paid_for(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->makeListing($seller, ['price' => 100_000_00]);
        $this->giveWallet($buyer, 100_000_00);
        $escrow = $this->escrows->create($buyer, $seller, 100_000_00, $listing);

        $listing->forceFill(['status' => 'removed'])->save();

        $this->expectException(EscrowException::class);
        $this->escrows->fund($escrow, $buyer);
    }

    public function test_escrow_for_a_listing_must_use_that_listings_seller(): void
    {
        $seller = User::factory()->create();
        $listing = $this->makeListing($seller);

        $this->expectException(EscrowException::class);
        $this->escrows->create(User::factory()->create(), User::factory()->create(), 100_000_00, $listing);
    }
}
