<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\EscrowStatus;
use App\Models\Escrow;
use App\Models\LedgerAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Independent check that the books are sound. It recomputes everything from the append-only entries
 * and compares against the cached balances and the escrow table. Any failure is logged critical and
 * the command exits non-zero so a scheduler or monitor can page someone.
 */
class AuditLedger extends Command
{
    protected $signature = 'ledger:audit';

    protected $description = 'Verify debits equal credits, cached balances match entries, and escrow holdings match open escrows';

    public function handle(): int
    {
        $problems = [];

        // 1. Double entry: total debits must equal total credits.
        $totals = DB::table('ledger_entries')->selectRaw('direction, SUM(amount) AS total')->groupBy('direction')->pluck('total', 'direction');
        if ((int) ($totals['debit'] ?? 0) !== (int) ($totals['credit'] ?? 0)) {
            $problems[] = sprintf('Books unbalanced: debits %d != credits %d.', $totals['debit'] ?? 0, $totals['credit'] ?? 0);
        }

        // 2. Every cached balance must equal the balance derived from its entries.
        $derived = DB::table('ledger_entries')
            ->selectRaw("ledger_account_id, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) AS net")
            ->groupBy('ledger_account_id')
            ->pluck('net', 'ledger_account_id');

        foreach (LedgerAccount::query()->cursor() as $account) {
            $net = (int) ($derived[$account->id] ?? 0);
            $expected = $account->type->isDebitNormal() ? $net : -$net;

            if ($account->balance !== $expected) {
                $problems[] = "Account #{$account->id} ({$account->type->value}) cached balance {$account->balance} != derived {$expected}.";
            }
            if ($account->balance < 0 && $account->type->forbidsOverdraft()) {
                $problems[] = "Account #{$account->id} ({$account->type->value}) is negative: {$account->balance}.";
            }
        }

        // 3. Money held in escrow accounts must equal the sum of escrows that are holding funds.
        $held = Escrow::whereIn('status', array_map(fn (EscrowStatus $s) => $s->value, [EscrowStatus::Funded, EscrowStatus::Delivered, EscrowStatus::Disputed]))->sum('amount');
        $inAccounts = LedgerAccount::totalBalance(AccountType::EscrowHolding);
        if ((int) $held !== $inAccounts) {
            $problems[] = "Escrow holding accounts hold {$inAccounts} but open escrows total {$held}.";
        }

        if ($problems === []) {
            $this->info('Ledger audit passed.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }
        Log::critical('Ledger audit failed', ['problems' => $problems]);

        return self::FAILURE;
    }
}
