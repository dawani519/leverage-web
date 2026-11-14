<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// MySQL unique indexes allow many NULLs, so system accounts (user_id NULL) are created
// exactly once here and only ever looked up afterwards, never created at runtime.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['gateway_clearing', 'platform_revenue'] as $type) {
            DB::table('ledger_accounts')->insert([
                'type' => $type,
                'user_id' => null,
                'currency' => 'NGN',
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ledger_accounts')->whereIn('type', ['gateway_clearing', 'platform_revenue'])->delete();
    }
};
