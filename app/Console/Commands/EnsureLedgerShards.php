<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\LedgerAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureLedgerShards extends Command
{
    protected $signature = 'ledger:ensure-shards';

    protected $description = 'Create any missing system ledger shard accounts up to config(ledger.shards)';

    public function handle(): int
    {
        $shards = (int) config('ledger.shards');
        $created = 0;

        foreach ([AccountType::EscrowHolding, AccountType::PlatformRevenue, AccountType::GatewayClearing] as $type) {
            for ($shard = 0; $shard < $shards; $shard++) {
                $exists = LedgerAccount::where('type', $type->value)->whereNull('user_id')->where('shard', $shard)->exists();
                if (! $exists) {
                    DB::table('ledger_accounts')->insert([
                        'type' => $type->value, 'user_id' => null, 'shard' => $shard, 'currency' => 'NGN',
                        'balance' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $created++;
                }
            }
        }

        $this->info("Created {$created} shard account(s); {$shards} shards per system account type.");

        return self::SUCCESS;
    }
}
