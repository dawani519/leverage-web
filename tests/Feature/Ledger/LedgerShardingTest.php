<?php

namespace Tests\Feature\Ledger;

use App\Enums\AccountType;
use App\Enums\Direction;
use App\Models\Escrow;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Escrow\EscrowService;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerShardingTest extends TestCase
{
    use RefreshDatabase;

    private function fundedEscrow(User $buyer, User $seller, int $amount): Escrow
    {
        app(LedgerService::class)->post('deposit', 'seed-'.uniqid(), [
            ['account' => LedgerAccount::system(AccountType::GatewayClearing), 'direction' => Direction::Debit, 'amount' => $amount],
            ['account' => LedgerAccount::forUser($buyer), 'direction' => Direction::Credit, 'amount' => $amount],
        ]);

        $escrows = app(EscrowService::class);

        return $escrows->fund($escrows->create($buyer, $seller, $amount), $buyer);
    }

    public function test_every_system_account_type_has_one_row_per_configured_shard(): void
    {
        $shards = (int) config('ledger.shards');

        foreach ([AccountType::EscrowHolding, AccountType::PlatformRevenue, AccountType::GatewayClearing] as $type) {
            $this->assertSame(
                $shards,
                LedgerAccount::where('type', $type->value)->whereNull('user_id')->count(),
                "{$type->value} should have {$shards} shards",
            );
        }
    }

    public function test_escrows_spread_across_shards_and_each_holds_only_its_own_funds(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        $escrows = collect(range(1, 24))->map(fn () => $this->fundedEscrow($buyer, $seller, 10_000_00));

        $usedShards = $escrows->pluck('shard')->unique();
        $this->assertGreaterThan(1, $usedShards->count(), 'All escrows landed on a single shard.');

        foreach ($escrows->groupBy('shard') as $shard => $group) {
            $this->assertSame(
                $group->sum('amount'),
                LedgerAccount::system(AccountType::EscrowHolding, $shard)->fresh()->balance,
            );
        }

        $this->assertSame(24 * 10_000_00, LedgerAccount::totalBalance(AccountType::EscrowHolding));
    }

    public function test_release_and_refund_debit_the_shard_the_escrow_was_funded_into(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $service = app(EscrowService::class);

        $a = $this->fundedEscrow($buyer, $seller, 100_000_00);
        $b = $this->fundedEscrow($buyer, $seller, 100_000_00);

        $service->release($a, $buyer);
        $service->refund($b, $seller);

        // No shard went negative (overdraft is blocked) and everything nets to zero.
        $this->assertSame(0, LedgerAccount::totalBalance(AccountType::EscrowHolding));
        $this->assertSame(
            0,
            LedgerAccount::where('type', AccountType::EscrowHolding->value)->whereNull('user_id')->where('balance', '!=', 0)->count(),
        );
        $this->assertSame(2_000_00, LedgerAccount::totalBalance(AccountType::PlatformRevenue));
    }

    public function test_raising_the_shard_count_never_remaps_funds_already_held(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        $escrow = $this->fundedEscrow($buyer, $seller, 100_000_00);
        $originalShard = $escrow->shard;

        config(['ledger.shards' => 16]);
        $this->artisan('ledger:ensure-shards')->assertSuccessful();

        // The shard is stored on the escrow, so release still debits the original account.
        app(EscrowService::class)->release($escrow, $buyer);

        $this->assertSame($originalShard, $escrow->fresh()->shard);
        $this->assertSame(0, LedgerAccount::system(AccountType::EscrowHolding, $originalShard)->fresh()->balance);
        $this->assertSame(0, LedgerAccount::totalBalance(AccountType::EscrowHolding));
    }

    public function test_ensure_shards_creates_only_what_is_missing_and_is_repeatable(): void
    {
        config(['ledger.shards' => 12]);

        $this->artisan('ledger:ensure-shards')->expectsOutputToContain('Created 12')->assertSuccessful();
        $this->artisan('ledger:ensure-shards')->expectsOutputToContain('Created 0')->assertSuccessful();

        $this->assertSame(12, LedgerAccount::where('type', 'escrow_holding')->whereNull('user_id')->count());
    }

    public function test_shard_for_is_deterministic_and_within_range(): void
    {
        foreach (['a', 'b', 'dep_01hxyz', 12345] as $key) {
            $shard = LedgerAccount::shardFor($key);

            $this->assertSame($shard, LedgerAccount::shardFor($key));
            $this->assertGreaterThanOrEqual(0, $shard);
            $this->assertLessThan((int) config('ledger.shards'), $shard);
        }
    }
}
