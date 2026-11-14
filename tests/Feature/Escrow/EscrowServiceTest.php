<?php

namespace Tests\Feature\Escrow;

use App\Enums\AccountType;
use App\Enums\Direction;
use App\Enums\EscrowStatus;
use App\Exceptions\EscrowException;
use App\Exceptions\InsufficientFundsException;
use App\Models\Dispute;
use App\Models\Escrow;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Escrow\EscrowService;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscrowServiceTest extends TestCase
{
    use RefreshDatabase;

    private EscrowService $escrows;

    private User $buyer;

    private User $seller;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escrows = app(EscrowService::class);
        $this->buyer = User::factory()->create();
        $this->seller = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
        $this->admin = $this->admin->fresh();
    }

    private function wallet(User $user): int
    {
        return LedgerAccount::forUser($user)->fresh()->balance;
    }

    private function system(AccountType $type): int
    {
        return LedgerAccount::totalBalance($type);
    }

    private function giveFunds(User $user, int $amount): void
    {
        app(LedgerService::class)->post('deposit', 'seed-'.$user->id.'-'.uniqid(), [
            ['account' => LedgerAccount::system(AccountType::GatewayClearing), 'direction' => Direction::Debit, 'amount' => $amount],
            ['account' => LedgerAccount::forUser($user), 'direction' => Direction::Credit, 'amount' => $amount],
        ]);
    }

    private function funded(int $amount = 100_000_00): Escrow
    {
        $this->giveFunds($this->buyer, $amount);
        $escrow = $this->escrows->create($this->buyer, $this->seller, $amount);

        return $this->escrows->fund($escrow, $this->buyer);
    }

    public function test_happy_path_moves_money_exactly_and_takes_the_fee(): void
    {
        $escrow = $this->funded(100_000_00);

        $this->assertSame(0, $this->wallet($this->buyer));
        $this->assertSame(100_000_00, $this->system(AccountType::EscrowHolding));

        $this->escrows->markDelivered($escrow, $this->seller);
        $this->escrows->release($escrow, $this->buyer);

        $this->assertSame(98_000_00, $this->wallet($this->seller));      // 2% default fee
        $this->assertSame(2_000_00, $this->system(AccountType::PlatformRevenue));
        $this->assertSame(0, $this->system(AccountType::EscrowHolding));
        $this->assertSame(EscrowStatus::Released, $escrow->fresh()->status);
        $this->assertNotNull($escrow->fresh()->closed_at);
    }

    public function test_fee_rounds_half_up_and_payout_plus_fee_equals_amount(): void
    {
        $escrow = $this->escrows->create($this->buyer, $this->seller, 100_333);

        $this->assertSame(2_007, $escrow->fee); // 2006.66 rounds to 2007
        $this->assertSame(100_333, $escrow->sellerPayout() + $escrow->fee);
    }

    public function test_fee_is_snapshotted_so_later_setting_changes_do_not_alter_open_escrows(): void
    {
        $escrow = $this->funded(100_000_00);
        app(\App\Services\SettingsService::class)->set('escrow.fee_bps', 1000);

        $this->escrows->release($escrow, $this->buyer);

        $this->assertSame(2_000_00, $this->system(AccountType::PlatformRevenue));
    }

    public function test_funding_without_enough_balance_fails_and_leaves_escrow_pending(): void
    {
        $this->giveFunds($this->buyer, 10_000_00);
        $escrow = $this->escrows->create($this->buyer, $this->seller, 100_000_00);

        try {
            $this->escrows->fund($escrow, $this->buyer);
            $this->fail('Should have thrown.');
        } catch (InsufficientFundsException) {
        }

        $this->assertSame(EscrowStatus::PendingPayment, $escrow->fresh()->status);
        $this->assertSame(10_000_00, $this->wallet($this->buyer));
        $this->assertSame(0, $this->system(AccountType::EscrowHolding));
    }

    public function test_wrong_actors_are_rejected(): void
    {
        $escrow = $this->funded();

        foreach ([
            fn () => $this->escrows->markDelivered($escrow, $this->buyer),
            fn () => $this->escrows->release($escrow, $this->seller),
            fn () => $this->escrows->refund($escrow, $this->buyer),
            fn () => $this->escrows->openDispute($escrow, User::factory()->create(), 'not my deal'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Unauthorised action succeeded.');
            } catch (EscrowException) {
            }
        }

        $this->assertSame(EscrowStatus::Funded, $escrow->fresh()->status);
        $this->assertSame(100_000_00, $this->system(AccountType::EscrowHolding));
    }

    public function test_buyer_and_seller_must_differ_and_amount_must_be_in_range(): void
    {
        foreach ([
            fn () => $this->escrows->create($this->buyer, $this->buyer, 100_000),
            fn () => $this->escrows->create($this->buyer, $this->seller, 5),
            fn () => $this->escrows->create($this->buyer, $this->seller, 9_000_000_00),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Invalid escrow was created.');
            } catch (EscrowException) {
            }
        }

        $this->assertSame(0, Escrow::count());
    }

    public function test_settled_escrow_cannot_be_settled_again(): void
    {
        $escrow = $this->funded();
        $this->escrows->release($escrow, $this->buyer);

        foreach ([
            fn () => $this->escrows->release($escrow, $this->buyer),
            fn () => $this->escrows->refund($escrow, $this->admin),
            fn () => $this->escrows->openDispute($escrow, $this->buyer, 'too late'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Second settlement succeeded.');
            } catch (EscrowException) {
            }
        }

        $this->assertSame(1, LedgerTransaction::where('type', 'escrow_release')->count());
        $this->assertSame(98_000_00, $this->wallet($this->seller));
    }

    public function test_cannot_release_an_escrow_that_was_never_funded(): void
    {
        $escrow = $this->escrows->create($this->buyer, $this->seller, 100_000_00);

        $this->expectException(EscrowException::class);
        $this->escrows->release($escrow, $this->buyer);
    }

    public function test_seller_refund_returns_the_full_amount_with_no_fee(): void
    {
        $escrow = $this->funded(50_000_00);

        $this->escrows->refund($escrow, $this->seller);

        $this->assertSame(50_000_00, $this->wallet($this->buyer));
        $this->assertSame(0, $this->system(AccountType::PlatformRevenue));
        $this->assertSame(EscrowStatus::Refunded, $escrow->fresh()->status);
    }

    public function test_cancel_only_works_before_payment(): void
    {
        $pending = $this->escrows->create($this->buyer, $this->seller, 100_000_00);
        $this->escrows->cancel($pending, $this->seller);
        $this->assertSame(EscrowStatus::Cancelled, $pending->fresh()->status);

        $funded = $this->funded();
        $this->expectException(EscrowException::class);
        $this->escrows->cancel($funded, $this->buyer);
    }

    public function test_auto_release_waits_for_the_window_and_pays_the_seller(): void
    {
        $escrow = $this->funded();
        $this->escrows->markDelivered($escrow, $this->seller);

        try {
            $this->escrows->autoRelease($escrow);
            $this->fail('Released before the window closed.');
        } catch (EscrowException) {
        }

        $this->travel(8)->days();
        $this->escrows->autoRelease($escrow);

        $this->assertSame(EscrowStatus::Released, $escrow->fresh()->status);
        $this->assertSame(98_000_00, $this->wallet($this->seller));
    }

    public function test_auto_release_never_touches_undelivered_or_disputed_escrows(): void
    {
        $undelivered = $this->funded();
        $this->travel(30)->days();

        try {
            $this->escrows->autoRelease($undelivered);
            $this->fail('Auto-released an undelivered escrow.');
        } catch (EscrowException) {
        }

        $this->buyer = User::factory()->create();
        $disputed = $this->funded();
        $this->escrows->markDelivered($disputed, $this->seller);
        $this->escrows->openDispute($disputed, $this->buyer, 'item not as described');
        $this->travel(30)->days();

        $this->assertNull($disputed->fresh()->auto_release_at);
        $this->expectException(EscrowException::class);
        $this->escrows->autoRelease($disputed);
    }

    public function test_dispute_freezes_funds_and_blocks_direct_settlement(): void
    {
        $escrow = $this->funded();
        $this->escrows->openDispute($escrow, $this->buyer, 'never arrived');

        $this->assertSame(EscrowStatus::Disputed, $escrow->fresh()->status);
        $this->assertSame(100_000_00, $this->system(AccountType::EscrowHolding));

        foreach ([
            fn () => $this->escrows->release($escrow, $this->buyer),
            fn () => $this->escrows->release($escrow, $this->admin),
            fn () => $this->escrows->refund($escrow, $this->seller),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Direct settlement bypassed the dispute.');
            } catch (EscrowException) {
            }
        }
    }

    public function test_admin_resolves_dispute_in_favour_of_buyer(): void
    {
        $escrow = $this->funded();
        $this->escrows->openDispute($escrow, $this->buyer, 'never arrived');
        $dispute = Dispute::firstOrFail();

        $this->escrows->resolveDispute($dispute, $this->admin, 'refund', 'Seller gave no tracking.');

        $this->assertSame(100_000_00, $this->wallet($this->buyer));
        $this->assertSame(0, $this->wallet($this->seller));
        $this->assertSame(EscrowStatus::Refunded, $escrow->fresh()->status);
        $this->assertSame('resolved', $dispute->fresh()->status);
        $this->assertSame($this->admin->id, $dispute->fresh()->resolved_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dispute.resolved', 'user_id' => $this->admin->id]);
    }

    public function test_admin_resolves_dispute_in_favour_of_seller(): void
    {
        $escrow = $this->funded();
        $this->escrows->openDispute($escrow, $this->seller, 'buyer is stalling');

        $this->escrows->resolveDispute(Dispute::firstOrFail(), $this->admin, 'release', 'Delivery proof accepted.');

        $this->assertSame(98_000_00, $this->wallet($this->seller));
        $this->assertSame(2_000_00, $this->system(AccountType::PlatformRevenue));
    }

    public function test_only_admins_resolve_disputes_and_only_once(): void
    {
        $escrow = $this->funded();
        $this->escrows->openDispute($escrow, $this->buyer, 'x');
        $dispute = Dispute::firstOrFail();

        try {
            $this->escrows->resolveDispute($dispute, $this->seller, 'release', 'self-serving');
            $this->fail('A non-admin resolved a dispute.');
        } catch (EscrowException) {
        }

        $this->escrows->resolveDispute($dispute, $this->admin, 'refund', 'ok');

        $this->expectException(EscrowException::class);
        $this->escrows->resolveDispute($dispute->fresh(), $this->admin, 'release', 'flip-flop');
    }

    public function test_escrow_holding_always_equals_the_sum_of_open_escrows(): void
    {
        $this->giveFunds($this->buyer, 1_000_000_00);

        $make = function (int $amount) {
            return $this->escrows->fund($this->escrows->create($this->buyer, $this->seller, $amount), $this->buyer);
        };

        $a = $make(100_000_00);
        $b = $make(200_000_00);
        $c = $make(300_000_00);
        $d = $make(150_000_00);

        $this->escrows->release($a, $this->buyer);
        $this->escrows->refund($b, $this->seller);
        $this->escrows->openDispute($c, $this->buyer, 'x');

        $open = Escrow::all()->filter(fn ($e) => $e->status->holdsFunds())->sum('amount');
        $this->assertSame(450_000_00, $open); // c (disputed) + d (funded)
        $this->assertSame($open, $this->system(AccountType::EscrowHolding));

        // Books balance across the whole ledger.
        $this->assertSame(
            \App\Models\LedgerEntry::where('direction', 'debit')->sum('amount'),
            \App\Models\LedgerEntry::where('direction', 'credit')->sum('amount'),
        );
    }
}
