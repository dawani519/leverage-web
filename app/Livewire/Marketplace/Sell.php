<?php

namespace App\Livewire\Marketplace;

use App\Models\Listing;
use App\Services\SettingsService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Sell an item')]
class Sell extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $category = '';

    public string $condition = 'new';

    public string $price = '';

    public string $location = '';

    public string $description = '';

    public bool $offersShipping = false;

    public bool $escrowProtected = true;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'category' => ['required', 'in:'.implode(',', array_keys(Listing::CATEGORIES))],
            'condition' => ['required', 'in:'.implode(',', array_keys(Listing::CONDITIONS))],
            'price' => ['required', 'string'],
            'location' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'photos' => ['required', 'array', 'min:3', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function save(SettingsService $settings)
    {
        $this->validate();

        $kobo = Money::parse($this->price);
        $min = $settings->int('escrow.min_amount_kobo');
        $max = $settings->int('escrow.max_amount_kobo');
        if ($kobo === null || $kobo < $min || $kobo > $max) {
            $this->addError('price', __('Enter a price between :min and :max.', ['min' => Money::naira($min), 'max' => Money::naira($max)]));

            return null;
        }

        $disk = Listing::imageDisk();
        $paths = array_map(fn ($photo) => $photo->store('listings', $disk), $this->photos);

        try {
            $listing = new Listing([
                'title' => trim($this->title),
                'description' => trim($this->description),
                'category' => $this->category,
                'condition' => $this->condition,
                'listing_type' => 'single',
                'price' => $kobo,
                'location' => trim($this->location),
                'offers_shipping' => $this->offersShipping,
                'leverage_fee_supported' => $this->escrowProtected,
                'images' => $paths,
            ]);
            $listing->seller_id = Auth::id();
            $listing->status = 'active';
            $listing->save();
        } catch (\Throwable $e) {
            // Do not leave orphaned uploads behind if the row could not be saved.
            Storage::disk($disk)->delete($paths);
            throw $e;
        }

        Flux::toast(variant: 'success', text: __('Your listing is live.'));

        return $this->redirectRoute('marketplace.show', $listing, navigate: true);
    }

    public function render()
    {
        return view('livewire.marketplace.sell');
    }
}
