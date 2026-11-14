<?php

namespace Tests\Feature\Ledger;

use App\Enums\AccountType;
use App\Enums\Direction;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\LedgerException;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = new LedgerService;
    }

    private function deposit(User $user, int $amount, string $key): LedgerTransaction
    {
        return $this->ledger->post('deposit', $key, [
            ['account' => LedgerAccount::system(AccountType::GatewayClearing), 'direction' => Direction::Debit, 'amount' => $amount],
            ['account' => LedgerAccount::forUser($user), 'direction' => Direction::Credit, 'amount' => $amount],
        ]);
    }

    public function test_deposit_credits_wallet_and_debits_clearing_asset(): void
    {
        $user = User::factory()->create();

        $this->deposit($user, 500_000, 'dep-1');

        $this->assertSame(500_000, LedgerAccount::forUser($user)->balance);
        $this->assertSame(500_000, LedgerAccount::system(AccountType::GatewayClearing)->balance);
        $this->assertSame(2, LedgerEntry::count());
    }

    public function test_unbalanced_transaction_is_rejected_and_writes_nothing(): void
    {
        $user = User::factory()->create();

        $this->expectException(LedgerException::class);
        try {
            $this->ledger->post('bad', 'bad-1', [
                ['account' => LedgerAccount::system(AccountType::GatewayClearing), 'direction' => Direction::Debit, 'amount' => 100],
                ['account' => LedgerAccount::forUser($user), 'direction' => Direction::Credit, 'amount' => 90],
            ]);
        } finally {
            $this->assertSame(0, LedgerTransaction::count());
        }
    }

    public function test_non_positive_amounts_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(LedgerException::class);
        $this->ledger->post('bad', 'bad-2', [
            ['account' => LedgerAccount::system(AccountType::GatewayClearing), 'direction' => Direction::Debit, 'amount' => 0],
            ['account' => LedgerAccount::forUser($user), 'direction' => Direction::Credit, 'amount' => 0],
        ]);
    }

    public function test_same_idempotency_key_replays_without_moving_money_twice(): void
    {
        $user = User::factory()->create();

        $first = $this->deposit($user, 100_000, 'dep-replay');
        $second = $this->deposit($user, 100_000, 'dep-replay');

        $this->assertTrue($first->is($second));
        $this->assertSame(100_000, LedgerAccount::forUser($user)->balance);
        $this->assertSame(1, LedgerTransaction::count());
    }

    public function test_wallet_cannot_be_overdrawn_and_failed_post_rolls_back(): void
    {
        $user = User::factory()->create();
        $this->deposit($user, 1_000, 'dep-small');

        try {
            $this->ledger->post('withdrawal', 'wd-1', [
                ['account' => LedgerAccount::forUser($user), 'direction' => Direction::Debit, 'amount' => 5_000],
                ['account' => LedgerAccount::system(AccountType::GatewayClearing), 'direction' => Direction::Credit, 'amount' => 5_000],
            ]);
            $this->fail('Overdraft should have thrown.');
        } catch (InsufficientFundsException) {
        }

        $this->assertSame(1_000, LedgerAccount::forUser($user)->balance);
        $this->assertSame(1_000, LedgerAccount::system(AccountType::GatewayClearing)->balance);
        $this->assertNull(LedgerTransaction::where('idempotency_key', 'wd-1')->first());
    }

    public function test_entries_and_transactions_are_immutable(): void
    {
        $user = User::factory()->create();
        $tx = $this->deposit($user, 1_000, 'dep-imm');

        $this->expectException(\LogicException::class);
        $tx->entries()->first()->update(['amount' => 1]);
    }

    public function test_cached_balances_always_equal_sum_of_entries_and_books_balance(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->deposit($a, 900_000, 'dep-a');
        $this->deposit($b, 300_000, 'dep-b');
        $this->ledger->post('transfer', 'tr-1', [
            ['account' => LedgerAccount::forUser($a), 'direction' => Direction::Debit, 'amount' => 250_000],
            ['account' => LedgerAccount::forUser($b), 'direction' => Direction::Credit, 'amount' => 250_000],
        ]);

        foreach (LedgerAccount::all() as $account) {
            $increase = $account->type->isDebitNormal() ? Direction::Debit : Direction::Credit;
            $derived = $account->entries->sum(fn ($e) => $e->direction === $increase ? $e->amount : -$e->amount);
            $this->assertSame($derived, $account->balance, "Account #{$account->id} drifted from its entries.");
        }

        $this->assertSame(
            LedgerEntry::where('direction', 'debit')->sum('amount'),
            LedgerEntry::where('direction', 'credit')->sum('amount'),
        );
    }
}
