<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SYSTEM_TYPES = ['escrow_holding', 'platform_revenue', 'gateway_clearing'];

    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('shard')->default(0)->after('user_id');
        });

        // The (type, user_id) unique key must now include the shard. Per-user accounts always use shard 0.
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropUnique(['type', 'user_id']);
            $table->unique(['type', 'user_id', 'shard']);
        });

        Schema::table('escrows', function (Blueprint $table) {
            // Fixed at creation so later changes to the shard count can never remap held funds.
            $table->unsignedSmallInteger('shard')->default(0)->after('fee_bps');
        });

        // Shard 0 already exists from earlier migrations. Add the rest. (MySQL unique keys allow many
        // NULL user_ids, so these rows are created here and by ledger:ensure-shards, never at runtime.)
        $shards = (int) config('ledger.shards', 8);
        foreach (self::SYSTEM_TYPES as $type) {
            for ($shard = 1; $shard < $shards; $shard++) {
                DB::table('ledger_accounts')->insert([
                    'type' => $type, 'user_id' => null, 'shard' => $shard, 'currency' => 'NGN',
                    'balance' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('ledger_accounts')->whereNull('user_id')->where('shard', '>', 0)->delete();

        Schema::table('escrows', fn (Blueprint $t) => $t->dropColumn('shard'));
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropUnique(['type', 'user_id', 'shard']);
            $table->unique(['type', 'user_id']);
        });
        Schema::table('ledger_accounts', fn (Blueprint $t) => $t->dropColumn('shard'));
    }
};
