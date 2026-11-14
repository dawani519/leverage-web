<?php

namespace Tests\Feature\Admin;

use App\Enums\EscrowStatus;
use App\Livewire\Admin\Brokers;
use App\Livewire\Admin\Disputes;
use App\Livewire\Admin\Listings;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Users;
use App\Models\Broker;
use App\Models\Dispute;
use App\Models\User;
use App\Services\Escrow\EscrowService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplaceData;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use BuildsMarketplaceData, RefreshDatabase;

    private const ROUTES = [
        'admin.overview', 'admin.users', 'admin.listings', 'admin.escrows', 'admin.disputes',
        'admin.deposits', 'admin.brokers', 'admin.settings', 'admin.audit',
    ];

    public function test_guests_are_sent_to_login_and_non_admins_get_a_404_on_every_admin_page(): void
    {
        // Guests first: actingAs() persists across requests within a test, so mixing them in one loop
        // would turn the later "guest" requests into logged-in ones.
        foreach (self::ROUTES as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach (self::ROUTES as $route) {
            $this->get(route($route))->assertNotFound();
        }
    }

    public function test_admins_can_open_every_admin_page(): void
    {
        $admin = $this->makeAdmin();

        foreach (self::ROUTES as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_admin_navigation_is_hidden_from_ordinary_users(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertDontSee(route('admin.settings'));
        $this->actingAs($this->makeAdmin())->get(route('dashboard'))->assertOk()->assertSee(route('admin.settings'));
    }

    public function test_the_role_column_cannot_be_mass_assigned(): void
    {
        $user = User::create(['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'secret-pass-1', 'role' => 'admin']);

        $this->assertNotSame('admin', $user->fresh()->role->value);
    }

    public function test_admin_can_suspend_and_reinstate_a_user_and_it_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        Livewire::actingAs($admin)->test(Users::class)->call('toggleSuspend', $user->id);
        $this->assertTrue($user->fresh()->isSuspended());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.suspended', 'user_id' => $admin->id, 'subject_id' => $user->id]);

        Livewire::actingAs($admin)->test(Users::class)->call('toggleSuspend', $user->id);
        $this->assertFalse($user->fresh()->isSuspended());
    }

    public function test_a_suspended_user_is_logged_out_on_their_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['suspended_at' => now()])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_admin_cannot_suspend_or_demote_themselves(): void
    {
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)->test(Users::class)->call('toggleSuspend', $admin->id)->call('setRole', $admin->id, 'user');

        $fresh = $admin->fresh();
        $this->assertFalse($fresh->isSuspended());
        $this->assertTrue($fresh->isAdmin());
    }

    public function test_changing_a_role_is_audited_and_rejects_unknown_roles(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        Livewire::actingAs($admin)->test(Users::class)->call('setRole', $user->id, 'admin');
        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed', 'subject_id' => $user->id]);

        Livewire::actingAs($admin)->test(Users::class)->call('setRole', $user->id, 'superuser')->assertStatus(422);
    }

    public function test_the_users_page_cannot_be_driven_by_a_non_admin(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Users::class)->assertNotFound();
    }

    public function test_admin_can_remove_and_restore_a_listing_but_not_touch_a_sold_one(): void
    {
        $admin = $this->makeAdmin();
        $listing = $this->makeListing(User::factory()->create());

        Livewire::actingAs($admin)->test(Listings::class)->call('toggleRemoved', $listing->id);
        $this->assertSame('removed', $listing->fresh()->status);

        Livewire::actingAs($admin)->test(Listings::class)->call('toggleRemoved', $listing->id);
        $this->assertSame('active', $listing->fresh()->status);

        $listing->forceFill(['status' => 'sold'])->save();
        Livewire::actingAs($admin)->test(Listings::class)->call('toggleRemoved', $listing->id);
        $this->assertSame('sold', $listing->fresh()->status);
    }

    public function test_admin_resolves_a_dispute_from_the_ui_and_it_needs_a_note(): void
    {
        $admin = $this->makeAdmin();
        [$buyer, $seller] = [User::factory()->create(), User::factory()->create()];
        $this->giveWallet($buyer, 100_000_00);
        $service = app(EscrowService::class);
        $escrow = $service->fund($service->create($buyer, $seller, 100_000_00), $buyer);
        $service->openDispute($escrow, $buyer, 'Item was not delivered at all.');
        $dispute = Dispute::firstOrFail();

        Livewire::actingAs($admin)->test(Disputes::class)->call('resolve', $dispute->id, 'refund')->assertHasErrors("notes.{$dispute->id}");
        $this->assertSame(EscrowStatus::Disputed, $escrow->fresh()->status);

        Livewire::actingAs($admin)->test(Disputes::class)
            ->set("notes.{$dispute->id}", 'Seller supplied no proof of delivery.')->call('resolve', $dispute->id, 'refund');

        $this->assertSame(EscrowStatus::Refunded, $escrow->fresh()->status);
        $this->assertSame(100_000_00, $this->wallet($buyer));
    }

    public function test_a_non_admin_cannot_resolve_a_dispute_even_by_calling_the_component_directly(): void
    {
        [$buyer, $seller] = [User::factory()->create(), User::factory()->create()];
        $this->giveWallet($buyer, 100_000_00);
        $service = app(EscrowService::class);
        $escrow = $service->fund($service->create($buyer, $seller, 100_000_00), $buyer);
        $service->openDispute($escrow, $buyer, 'Item was not delivered at all.');
        $dispute = Dispute::firstOrFail();

        Livewire::actingAs($seller)->test(Disputes::class)->assertNotFound();

        $this->assertSame(EscrowStatus::Disputed, $escrow->fresh()->status);
    }

    public function test_broker_approval_grants_the_role_and_rejection_needs_a_reason(): void
    {
        $admin = $this->makeAdmin();
        $applicant = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$applicant, $other] as $u) {
            $b = new Broker(['first_name' => 'A', 'last_name' => 'B', 'dob' => '1990-01-01', 'state' => 'Lagos', 'lga' => 'Ikeja', 'park_name' => 'P', 'phone' => '+2348000000000']);
            $b->user_id = $u->id;
            $b->save();
        }
        $first = Broker::where('user_id', $applicant->id)->firstOrFail();
        $second = Broker::where('user_id', $other->id)->firstOrFail();

        Livewire::actingAs($admin)->test(Brokers::class)->call('review', $first->id, 'approved');
        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('broker', $applicant->fresh()->role->value);

        Livewire::actingAs($admin)->test(Brokers::class)->call('review', $second->id, 'rejected')->assertHasErrors("notes.{$second->id}");
        Livewire::actingAs($admin)->test(Brokers::class)->set("notes.{$second->id}", 'ID could not be verified')->call('review', $second->id, 'rejected');
        $this->assertSame('rejected', $second->fresh()->status);
        $this->assertSame('user', $other->fresh()->role->value);

        // Already reviewed: a second decision must not flip it.
        Livewire::actingAs($admin)->test(Brokers::class)->call('review', $first->id, 'rejected');
        $this->assertSame('approved', $first->fresh()->status);
    }

    public function test_approving_a_broker_never_downgrades_an_admin(): void
    {
        $admin = $this->makeAdmin();
        $other = $this->makeAdmin();
        $b = new Broker(['first_name' => 'A', 'last_name' => 'B', 'dob' => '1990-01-01', 'state' => 'Lagos', 'lga' => 'Ikeja', 'park_name' => 'P', 'phone' => '+2348000000000']);
        $b->user_id = $other->id;
        $b->save();

        Livewire::actingAs($admin)->test(Brokers::class)->call('review', $b->id, 'approved');

        $this->assertTrue($other->fresh()->isAdmin());
    }

    public function test_settings_are_saved_validated_and_audited_without_leaking_the_secret(): void
    {
        $admin = $this->makeAdmin();

        $component = Livewire::actingAs($admin)->test(Settings::class)
            ->set('feePercent', '3.5')->set('autoReleaseDays', '10')->set('paystackSecret', 'sk_test_supersecret123')
            ->call('save')->assertHasNoErrors();

        $settings = app(SettingsService::class);
        $this->assertSame(350, $settings->int('escrow.fee_bps'));
        $this->assertSame(10, $settings->int('escrow.auto_release_days'));
        $this->assertSame('sk_test_supersecret123', $settings->get('paystack.secret_key'));

        // The secret is write-only: never in the component state, the rendered page, or the audit log.
        $component->assertSet('paystackSecret', '')->assertSet('secretIsSet', true)->assertDontSee('sk_test_supersecret123');
        $this->assertStringNotContainsString('sk_test_supersecret123', json_encode(\App\Models\AuditLog::all()->toArray()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'user_id' => $admin->id]);

        // A fresh load of the form still does not know the secret.
        Livewire::actingAs($admin)->test(Settings::class)->assertSet('paystackSecret', '')->assertDontSee('sk_test_supersecret123');
    }

    public function test_settings_reject_out_of_range_values(): void
    {
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)->test(Settings::class)->set('feePercent', '60')->call('save')->assertHasErrors('feePercent');
        Livewire::actingAs($admin)->test(Settings::class)->set('autoReleaseDays', '0')->call('save')->assertHasErrors('autoReleaseDays');
        Livewire::actingAs($admin)->test(Settings::class)->set('minAmount', '1000')->set('maxAmount', '500')->call('save')->assertHasErrors('minAmount');
        Livewire::actingAs($admin)->test(Settings::class)->set('paystackSecret', 'sk bad; key')->call('save')->assertHasErrors('paystackSecret');
    }

    public function test_the_paystack_secret_can_be_removed(): void
    {
        $admin = $this->makeAdmin();
        app(SettingsService::class)->set('paystack.secret_key', 'sk_test_abc');

        Livewire::actingAs($admin)->test(Settings::class)->assertSet('secretIsSet', true)->call('clearSecret')->assertSet('secretIsSet', false);

        $this->assertNull(app(SettingsService::class)->get('paystack.secret_key'));
    }

    public function test_changed_fee_applies_to_new_escrows(): void
    {
        $admin = $this->makeAdmin();
        Livewire::actingAs($admin)->test(Settings::class)->set('feePercent', '5')->call('save');

        $escrow = app(EscrowService::class)->create(User::factory()->create(), User::factory()->create(), 100_000_00);

        $this->assertSame(5_000_00, $escrow->fee);
    }
}
