<?php

namespace App\Livewire\Marketplace;

use App\Models\Listing;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::public')]
#[Title('Marketplace')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $condition = '';

    #[Url]
    public string $sort = 'newest';

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'category', 'condition', 'sort'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function listings()
    {
        $term = trim($this->search);
        // Escape LIKE wildcards so a user typing % or _ searches for the literal character.
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return Listing::active()
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like)))
            ->when(array_key_exists($this->category, Listing::CATEGORIES), fn ($q) => $q->where('category', $this->category))
            ->when(array_key_exists($this->condition, Listing::CONDITIONS), fn ($q) => $q->where('condition', $this->condition))
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('price'), fn ($q) => $this->sort === 'price_desc' ? $q->orderByDesc('price') : $q->latest('id'))
            ->paginate(12);
    }

    public function render()
    {
        return view('livewire.marketplace.index');
    }
}
