<?php

namespace Tests\Feature\Marketplace;

use App\Livewire\Brokers\Apply;
use App\Livewire\Chat\Index as ChatIndex;
use App\Livewire\Marketplace\Show;
use App\Models\Broker;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplaceData;
use Tests\TestCase;

class ChatAndBrokerTest extends TestCase
{
    use BuildsMarketplaceData, RefreshDatabase;

    private function thread(User $buyer, User $seller): Conversation
    {
        return Conversation::forceCreate(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
    }

    public function test_messaging_a_seller_opens_one_thread_and_reuses_it(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->makeListing($seller);

        Livewire::actingAs($buyer)->test(Show::class, ['listing' => $listing])->call('messageSeller');
        Livewire::actingAs($buyer)->test(Show::class, ['listing' => $listing])->call('messageSeller');

        $this->assertSame(1, Conversation::count());
    }

    public function test_a_seller_cannot_message_themselves(): void
    {
        $seller = User::factory()->create();
        $listing = $this->makeListing($seller);

        Livewire::actingAs($seller)->test(Show::class, ['listing' => $listing])->call('messageSeller');

        $this->assertSame(0, Conversation::count());
    }

    public function test_participants_can_send_and_read_messages(): void
    {
        [$buyer, $seller] = [User::factory()->create(), User::factory()->create()];
        $thread = $this->thread($buyer, $seller);

        Livewire::actingAs($buyer)->test(ChatIndex::class, ['conversationId' => $thread->id])
            ->set('body', 'Is it still available?')->call('send')->assertHasNoErrors()->assertSet('body', '');

        Livewire::actingAs($seller)->test(ChatIndex::class, ['conversationId' => $thread->id])->assertSee('Is it still available?');

        $this->assertNotNull(Message::firstOrFail()->read_at, 'Reading marks the other side\'s messages as read.');
    }

    public function test_an_outsider_cannot_read_or_post_into_someone_elses_thread_even_by_tampering_the_id(): void
    {
        [$buyer, $seller, $outsider] = [User::factory()->create(), User::factory()->create(), User::factory()->create()];
        $thread = $this->thread($buyer, $seller);
        Message::forceCreate(['conversation_id' => $thread->id, 'sender_id' => $buyer->id, 'body' => 'private words']);

        Livewire::actingAs($outsider)->test(ChatIndex::class, ['conversationId' => $thread->id])
            ->assertDontSee('private words')
            ->set('body', 'let me in')->call('send')->assertNotFound();

        $this->assertSame(1, Message::count());
    }

    public function test_select_ignores_conversations_the_user_is_not_part_of(): void
    {
        [$buyer, $seller, $outsider] = [User::factory()->create(), User::factory()->create(), User::factory()->create()];
        $thread = $this->thread($buyer, $seller);

        Livewire::actingAs($outsider)->test(ChatIndex::class)->call('select', $thread->id)->assertSet('conversationId', null);
    }

    public function test_message_validation_and_length_limit(): void
    {
        [$buyer, $seller] = [User::factory()->create(), User::factory()->create()];
        $thread = $this->thread($buyer, $seller);
        $component = Livewire::actingAs($buyer)->test(ChatIndex::class, ['conversationId' => $thread->id]);

        $component->set('body', '')->call('send')->assertHasErrors('body');
        $component->set('body', str_repeat('a', 2001))->call('send')->assertHasErrors('body');
        $this->assertSame(0, Message::count());
    }

    public function test_message_text_is_escaped_not_rendered_as_html(): void
    {
        [$buyer, $seller] = [User::factory()->create(), User::factory()->create()];
        $thread = $this->thread($buyer, $seller);
        Message::forceCreate(['conversation_id' => $thread->id, 'sender_id' => $seller->id, 'body' => '<script>alert(1)</script>']);

        Livewire::actingAs($buyer)->test(ChatIndex::class, ['conversationId' => $thread->id])
            ->assertDontSeeHtml('<script>alert(1)</script>')->assertSee('<script>alert(1)</script>', escape: true);
    }

    public function test_sending_is_rate_limited(): void
    {
        [$buyer, $seller] = [User::factory()->create(), User::factory()->create()];
        $thread = $this->thread($buyer, $seller);
        $component = Livewire::actingAs($buyer)->test(ChatIndex::class, ['conversationId' => $thread->id]);

        foreach (range(1, 30) as $i) {
            $component->set('body', "m$i")->call('send')->assertHasNoErrors();
        }
        $component->set('body', 'one too many')->call('send')->assertHasErrors('body');

        $this->assertSame(30, Message::count());
    }

    // ---- brokers ----

    private function application(): array
    {
        return [
            'firstName' => 'Ada', 'lastName' => 'Obi', 'dob' => '1990-05-04', 'state' => 'Lagos',
            'lga' => 'Ikeja', 'parkName' => 'Ojota Park', 'phone' => '+2348012345678',
        ];
    }

    public function test_a_user_can_apply_once_and_starts_as_pending(): void
    {
        $user = User::factory()->create();
        $form = Livewire::actingAs($user)->test(Apply::class);
        foreach ($this->application() as $field => $value) {
            $form->set($field, $value);
        }
        $form->call('save')->assertHasNoErrors();

        $broker = Broker::firstOrFail();
        $this->assertSame('pending', $broker->status);
        $this->assertSame($user->id, $broker->user_id);

        Livewire::actingAs($user)->test(Apply::class)->call('save')->assertForbidden();
        $this->assertSame(1, Broker::count());
    }

    public function test_applicants_must_be_adults_with_a_valid_state_and_phone(): void
    {
        $form = Livewire::actingAs(User::factory()->create())->test(Apply::class);
        foreach ($this->application() as $field => $value) {
            $form->set($field, $value);
        }

        $form->set('dob', now()->subYears(17)->toDateString())->call('save')->assertHasErrors('dob');
        $form->set('dob', '1990-05-04')->set('state', 'Atlantis')->call('save')->assertHasErrors('state');
        $form->set('state', 'Lagos')->set('phone', 'call me')->call('save')->assertHasErrors('phone');
        $this->assertSame(0, Broker::count());
    }

    public function test_an_applicant_cannot_approve_themselves_by_posting_a_status(): void
    {
        $user = User::factory()->create();
        $form = Livewire::actingAs($user)->test(Apply::class);
        foreach ($this->application() as $field => $value) {
            $form->set($field, $value);
        }
        $form->call('save');

        $this->assertSame('pending', Broker::firstOrFail()->status);
        $this->assertNotSame('broker', $user->fresh()->role->value);
    }
}
