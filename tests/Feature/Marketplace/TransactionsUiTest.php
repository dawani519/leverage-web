<?php

namespace Tests\Feature\Marketplace;

use App\Enums\EscrowStatus;
use App\Livewire\Transactions\Create;
use App\Livewire\Transactions\Index;
use App\Livewire\Transactions\Show;
use App\Livewire\Wallet\Index as WalletIndex;
use App\Models\Dispute;
use App\Models\Escrow;
use App\Models\User;
use App\Services\Escrow\EscrowService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplaceData;
use Tests\TestCase;

class TransactionsUiTest extends TestCase
{
    use BuildsMarketplaceData, RefreshDatabase;

    private User $buyer;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create();
        $this->seller = User::factory()->create();
    }

    private function escrow(int $amount = 100_000_00, bool $fund = false): Escrow
    {
        $escrow = app(EscrowService::class)->create($this->buyer, $this->seller, $amount, null, 'Test deal');
        if ($fund) {
            $this->giveWallet($this->buyer, $amount);
            $escrow = app(EscrowService::class)->fund($escrow, $this->buyer);
        }

        return $escrow;
    }

    public function test_only_the_two_parties_and_admins_can_open_a_transaction(): void
    {
        $escrow = $this->escrow();
        $url = route('transactions.show', $escrow->uuid);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->actingAs($this->buyer)->get($url)->assertOk();
        $this->actingAs($this->seller)->get($url)->assertOk();
        $this->actingAs($this->makeAdmin())->get($url)->assertOk();
    }

    public function test_transactions_are_addressed_by_uuid_not_a_guessable_id(): void
    {
        $escrow = $this->escrow();

        $this->actingAs($this->buyer)->get('/transactions/'.$escrow->id)->assertNotFound();
    }

    public function test_the_full_happy_path_through_the_ui(): void
    {
        $escrow = $this->escrow(100_000_00);
        $this->giveWallet($this->buyer, 100_000_00);

        Livewire::actingAs($this->buyer)->test(Show::class, ['escrow' => $escrow])->call('act', 'fund')->assertSee('Paid, awaiting delivery');
        Livewire::actingAs($this->seller)->test(Show::class, ['escrow' => $escrow])->call('act', 'deliver')->assertSee('Delivered');
        Livewire::actingAs($this->buyer)->test(Show::class, ['escrow' => $escrow])->call('act', 'release')->assertSee('Completed');

        $this->assertSame(EscrowStatus::Released, $escrow->fresh()->status);
        $this->assertSame(98_000_00, $this->wallet($this->seller));
        $this->assertSame(0, $this->wallet($this->buyer));
    }

    public function test_funding_with_too_little_money_changes_nothing(): void
    {
        $escrow = $this->escrow(100_000_00);
        $this->giveWallet($this->buyer, 5_000_00);

        Livewire::actingAs($this->buyer)->test(Show::class, ['escrow' => $escrow])->call('act', 'fund');

        $this->assertSame(EscrowStatus::PendingPayment, $escrow->fresh()->status);
        $this->assertSame(5_000_00, $this->wallet($this->buyer));
    }

    public function test_a_party_cannot_perform_the_other_partys_action(): void
    {
        $escrow = $this->escrow(100_000_00, fund: true);

        // The seller trying to release their own payout, and the buyer trying to mark it delivered.
        Livewire::actingAs($this->seller)->test(Show::class, ['escrow' => $escrow])->call('act', 'release');
        Livewire::actingAs($this->buyer)->test(Show::class, ['escrow' => $escrow])->call('act', 'deliver');

        $this->assertSame(EscrowStatus::Funded, $escrow->fresh()->status);
        $this->assertSame(0, $this->wallet($this->seller));
    }

    public function test_an_admin_viewing_a_transaction_cannot_act_on_it_from_that_page(): void
    {
        $escrow = $this->escrow(100_000_00, fund: true);

        Livewire::actingAs($this->makeAdmin())->test(Show::class, ['escrow' => $escrow])->call('act', 'release')->assertForbidden();

        $this->assertSame(EscrowStatus::Funded, $escrow->fresh()->status);
    }

    public function test_an_unknown_action_name_is_rejected(): void
    {
        $escrow = $this->escrow(100_000_00, fund: true);

        Livewire::actingAs($this->buyer)->test(Show::class, ['escrow' => $escrow])->call('act', 'steal')->assertStatus(422);
    }

    public function test_opening_a_dispute_needs_a_real_reason_and_freezes_the_money(): void
    {
        $escrow = $this->escrow(100_000_00, fund: true);

        Livewire::actingAs($this->buyer)->test(Show::class, ['escrow' => $escrow])
            ->set('disputeReason', 'bad')->call('openDispute')->assertHasErrors('disputeReason');

        Livewire::actingAs($this->buyer)->test(Show::class, ['escrow' => $escrow])
            ->set('disputeReason', 'The item never arrived after two weeks.')->call('openDispute')->assertHasNoErrors();

        $this->assertSame(EscrowStatus::Disputed, $escrow->fresh()->status);
        $this->assertSame(1, Dispute::count());
    }

    public function test_index_lists_only_my_transactions_and_filters_by_role(): void
    {
        $mine = $this->escrow(10_000_00);
        $stranger = User::factory()->create();
        $other = app(EscrowService::class)->create($stranger, User::factory()->create(), 20_000_00, null, 'Not mine');

        Livewire::actingAs($this->buyer)->test(Index::class)->assertSee('Test deal')->assertDontSee('Not mine');
        Livewire::actingAs($this->seller)->test(Index::class)->set('role', 'selling')->assertSee('Test deal');
        Livewire::actingAs($this->seller)->test(Index::class)->set('role', 'buying')->assertDontSee('Test deal');
    }

    public function test_creating_a_transaction_by_email(): void
    {
        Livewire::actingAs($this->buyer)->test(Create::class)
            ->set('role', 'buyer')->set('email', $this->seller->email)->set('amount', '75,000')->set('description', 'Used camera')
            ->call('save')->assertHasNoErrors();

        $escrow = Escrow::firstOrFail();
        $this->assertSame($this->buyer->id, $escrow->buyer_id);
        $this->assertSame($this->seller->id, $escrow->seller_id);
        $this->assertSame(75_000_00, $escrow->amount);
    }

    public function test_creating_as_seller_flips_the_roles(): void
    {
        Livewire::actingAs($this->seller)->test(Create::class)
            ->set('role', 'seller')->set('email', $this->buyer->email)->set('amount', '10000')->set('description', 'Logo design')
            ->call('save')->assertHasNoErrors();

        $escrow = Escrow::firstOrFail();
        $this->assertSame($this->buyer->id, $escrow->buyer_id);
        $this->assertSame($this->seller->id, $escrow->seller_id);
    }

    public function test_unknown_email_and_own_email_give_the_same_vague_error(): void
    {
        $unknown = Livewire::actingAs($this->buyer)->test(Create::class)
            ->set('email', 'nobody@example.com')->set('amount', '10000')->set('description', 'x')->call('save');
        $self = Livewire::actingAs($this->buyer)->test(Create::class)
            ->set('email', $this->buyer->email)->set('amount', '10000')->set('description', 'x')->call('save');

        $unknown->assertHasErrors('email');
        $self->assertHasErrors('email');
        $this->assertSame(0, Escrow::count());
    }

    public function test_wallet_shows_balance_and_history(): void
    {
        $this->giveWallet($this->buyer, 12_500_00);

        Livewire::actingAs($this->buyer)->test(WalletIndex::class)->assertSee('₦12,500.00')->assertSee('Wallet top-up');
    }

    public function test_wallet_deposit_redirects_to_paystack_checkout(): void
    {
        app(SettingsService::class)->set('paystack.secret_key', 'sk_test_ui');
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'access_code' => 'xyz', 'reference' => 'r'],
        ])]);

        Livewire::actingAs($this->buyer)->test(WalletIndex::class)->set('amount', '5,000')->call('deposit')
            ->assertRedirect('https://checkout.paystack.com/xyz');
    }

    public function test_wallet_deposit_rejects_bad_amounts_without_calling_paystack(): void
    {
        Http::fake();

        $component = Livewire::actingAs($this->buyer)->test(WalletIndex::class);
        $component->set('amount', 'abc')->call('deposit')->assertHasErrors('amount');
        $component->set('amount', '0.50')->call('deposit')->assertHasErrors('amount');

        Http::assertNothingSent();
    }

    public function test_wallet_deposit_fails_cleanly_when_paystack_is_not_configured(): void
    {
        Livewire::actingAs($this->buyer)->test(WalletIndex::class)->set('amount', '5000')->call('deposit')->assertHasErrors('amount');
    }
}
