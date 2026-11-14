<?php

namespace Tests\Feature\Ops;

use App\Enums\AccountType;
use App\Enums\Direction;
use App\Models\Escrow;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Escrow\EscrowService;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedActivity(): Escrow
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        app(LedgerService::class)->post('deposit', 'audit-seed', [
            ['account' => LedgerAccount::system(AccountType::GatewayClearing), 'direction' => Direction::Debit, 'amount' => 500_000_00],
            ['account' => LedgerAccount::forUser($buyer), 'direction' => Direction::Credit, 'amount' => 500_000_00],
        ]);

        $escrows = app(EscrowService::class);

        return $escrows->fund($escrows->create($buyer, $seller, 200_000_00), $buyer);
    }

    public function test_every_response_carries_a_request_id(): void
    {
        $id = $this->get('/')->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $id);
    }

    public function test_a_safe_incoming_request_id_is_kept_and_an_unsafe_one_is_replaced(): void
    {
        $this->withHeader('X-Request-Id', 'lb-req-12345')->get('/')->assertHeader('X-Request-Id', 'lb-req-12345');

        $response = $this->withHeader('X-Request-Id', "bad id\r\nX-Injected: 1")->get('/');
        $this->assertStringNotContainsString('Injected', (string) $response->headers->get('X-Request-Id'));
        $this->assertNotSame("bad id\r\nX-Injected: 1", $response->headers->get('X-Request-Id'));
    }

    public function test_health_reports_ok_per_component_without_leaking_details(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'checks' => ['database' => true, 'cache' => true, 'queue_backlog' => true]]);
    }

    public function test_audit_passes_on_a_clean_ledger(): void
    {
        $this->seedActivity();

        $this->artisan('ledger:audit')->expectsOutputToContain('Ledger audit passed')->assertSuccessful();
    }

    public function test_audit_passes_on_an_empty_ledger(): void
    {
        $this->artisan('ledger:audit')->assertSuccessful();
    }

    public function test_audit_catches_a_tampered_cached_balance(): void
    {
        $this->seedActivity();
        DB::table('ledger_accounts')->where('type', 'user_wallet')->limit(1)->update(['balance' => 999_999_999]);

        $this->artisan('ledger:audit')->expectsOutputToContain('cached balance')->assertFailed();
    }

    public function test_audit_catches_unbalanced_books(): void
    {
        $this->seedActivity();
        $tx = DB::table('ledger_transactions')->value('id');
        $account = DB::table('ledger_accounts')->value('id');
        DB::table('ledger_entries')->insert([
            'ledger_transaction_id' => $tx, 'ledger_account_id' => $account,
            'direction' => 'credit', 'amount' => 1, 'balance_after' => 0,
        ]);

        $this->artisan('ledger:audit')->expectsOutputToContain('Books unbalanced')->assertFailed();
    }

    public function test_audit_catches_escrow_holdings_that_disagree_with_open_escrows(): void
    {
        $escrow = $this->seedActivity();
        DB::table('escrows')->where('id', $escrow->id)->update(['amount' => 1]);

        $this->artisan('ledger:audit')->expectsOutputToContain('open escrows total')->assertFailed();
    }
}
