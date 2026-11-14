<?php

namespace Tests\Feature\Marketplace;

use App\Livewire\Marketplace\Index;
use App\Livewire\Marketplace\MyListings;
use App\Livewire\Marketplace\Sell;
use App\Livewire\Marketplace\Show;
use App\Models\Escrow;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplaceData;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use BuildsMarketplaceData, RefreshDatabase;

    public function test_guests_can_browse_and_view_listings(): void
    {
        $listing = $this->makeListing(User::factory()->create());

        $this->get('/')->assertOk()->assertSee('iPhone 13 Pro');
        $this->get(route('marketplace.show', $listing))->assertOk()->assertSee('Buy with escrow');
    }

    public function test_search_category_and_sort_filter_the_results(): void
    {
        $seller = User::factory()->create();
        $this->makeListing($seller, ['title' => 'Red bicycle', 'category' => 'other', 'price' => 50_000_00]);
        $this->makeListing($seller, ['title' => 'Blue laptop', 'category' => 'electronics', 'price' => 300_000_00]);

        Livewire::test(Index::class)->set('search', 'bicycle')->assertSee('Red bicycle')->assertDontSee('Blue laptop');
        Livewire::test(Index::class)->set('category', 'electronics')->assertSee('Blue laptop')->assertDontSee('Red bicycle');

        Livewire::test(Index::class)->set('sort', 'price_asc')->assertSeeInOrder(['Red bicycle', 'Blue laptop']);
        Livewire::test(Index::class)->set('sort', 'price_desc')->assertSeeInOrder(['Blue laptop', 'Red bicycle']);
    }

    public function test_search_treats_percent_and_underscore_as_literal_characters(): void
    {
        $seller = User::factory()->create();
        $this->makeListing($seller, ['title' => '100% cotton shirt']);
        $this->makeListing($seller, ['title' => 'Plain shirt']);

        Livewire::test(Index::class)->set('search', '%')->assertSee('100% cotton shirt')->assertDontSee('Plain shirt');
    }

    public function test_only_active_listings_appear_in_browse(): void
    {
        $seller = User::factory()->create();
        $this->makeListing($seller, ['title' => 'Paused thing', 'status' => 'paused']);
        $this->makeListing($seller, ['title' => 'Removed thing', 'status' => 'removed']);
        $this->makeListing($seller, ['title' => 'Sold thing', 'status' => 'sold']);

        Livewire::test(Index::class)->assertDontSee('Paused thing')->assertDontSee('Removed thing')->assertDontSee('Sold thing');
    }

    public function test_inactive_listing_is_visible_only_to_its_seller_and_admins(): void
    {
        $seller = User::factory()->create();
        $listing = $this->makeListing($seller, ['status' => 'removed']);

        $this->get(route('marketplace.show', $listing))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('marketplace.show', $listing))->assertNotFound();
        $this->actingAs($seller)->get(route('marketplace.show', $listing))->assertOk();
        $this->actingAs($this->makeAdmin())->get(route('marketplace.show', $listing))->assertOk();
    }

    public function test_selling_requires_login(): void
    {
        $this->get(route('marketplace.sell'))->assertRedirect(route('login'));
    }

    public function test_seller_can_publish_a_listing_with_photos(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create();

        Livewire::actingAs($seller)->test(Sell::class)
            ->set('title', 'Standing desk')
            ->set('category', 'home')
            ->set('condition', 'used')
            ->set('price', '85,000')
            ->set('location', 'Abuja')
            ->set('description', 'Electric, height adjustable, works perfectly.')
            ->set('photos', [$this->fakePhoto('1.png'), $this->fakePhoto('2.png'), $this->fakePhoto('3.png')])
            ->call('save')
            ->assertHasNoErrors();

        $listing = Listing::firstOrFail();
        $this->assertSame(85_000_00, $listing->price);
        $this->assertSame($seller->id, $listing->seller_id);
        $this->assertSame('active', $listing->status);
        $this->assertCount(3, $listing->images);
        Storage::disk('public')->assertExists($listing->images[0]);
    }

    public function test_fewer_than_three_photos_or_a_bad_price_is_rejected(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create();
        $form = fn () => Livewire::actingAs($seller)->test(Sell::class)
            ->set('title', 'Standing desk')->set('category', 'home')->set('location', 'Abuja')
            ->set('description', 'Electric, height adjustable, works perfectly.');

        $form()->set('price', '85000')->set('photos', [$this->fakePhoto('1.png')])->call('save')->assertHasErrors('photos');

        $threePhotos = [$this->fakePhoto('1.png'), $this->fakePhoto('2.png'), $this->fakePhoto('3.png')];
        $form()->set('price', 'free')->set('photos', $threePhotos)->call('save')->assertHasErrors('price');
        $form()->set('price', '0.50')->set('photos', $threePhotos)->call('save')->assertHasErrors('price'); // below the minimum

        $this->assertSame(0, Listing::count());
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create();
        $script = \Illuminate\Http\UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;');

        Livewire::actingAs($seller)->test(Sell::class)->set('photos', [$script, $script, $script])->call('save')->assertHasErrors('photos.0');
    }

    public function test_sellers_manage_only_their_own_listings(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $myListing = $this->makeListing($mine);
        $otherListing = $this->makeListing($theirs, ['title' => 'Not yours']);

        $component = Livewire::actingAs($mine)->test(MyListings::class)->assertSee('iPhone 13 Pro')->assertDontSee('Not yours');

        $component->call('togglePause', $myListing->id);
        $this->assertSame('paused', $myListing->fresh()->status);

        // Livewire renders a missing model as a 404 response rather than throwing it.
        $component->call('togglePause', $otherListing->id)->assertNotFound();
        $this->assertSame('active', $otherListing->fresh()->status, 'Someone else\'s listing must be untouched.');
    }

    public function test_a_seller_cannot_resume_a_listing_an_admin_removed(): void
    {
        $seller = User::factory()->create();
        $listing = $this->makeListing($seller, ['status' => 'removed']);

        Livewire::actingAs($seller)->test(MyListings::class)->call('togglePause', $listing->id);

        $this->assertSame('removed', $listing->fresh()->status);
    }

    public function test_buying_creates_a_pending_escrow_and_redirects_to_it(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->makeListing($seller);

        Livewire::actingAs($buyer)->test(Show::class, ['listing' => $listing])->call('buyWithEscrow');

        $escrow = Escrow::firstOrFail();
        $this->assertSame($buyer->id, $escrow->buyer_id);
        $this->assertSame($seller->id, $escrow->seller_id);
        $this->assertSame(450_000_00, $escrow->amount);
        $this->assertSame($listing->id, $escrow->listing_id);
    }

    public function test_a_seller_cannot_buy_their_own_listing(): void
    {
        $seller = User::factory()->create();
        $listing = $this->makeListing($seller);

        Livewire::actingAs($seller)->test(Show::class, ['listing' => $listing])->call('buyWithEscrow');

        $this->assertSame(0, Escrow::count());
    }

    public function test_guests_are_sent_to_login_when_they_try_to_buy(): void
    {
        $listing = $this->makeListing(User::factory()->create());

        Livewire::test(Show::class, ['listing' => $listing])->call('buyWithEscrow')->assertRedirect(route('login'));

        $this->assertSame(0, Escrow::count());
    }
}
