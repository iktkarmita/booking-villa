<?php

namespace App\Livewire\Site;

use App\Models\Villa;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VillaList extends Component
{
    public function render(): View
    {
        $villas = Villa::query()
            ->where('is_active', true)
            ->with(['images', 'rooms' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('id')
            ->get()
            ->filter(fn (Villa $villa) => $villa->rooms->isNotEmpty());

        return view('livewire.site.villa-list', ['villas' => $villas]);
    }
}
