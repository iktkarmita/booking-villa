<?php

namespace App\Livewire\Site;

use App\Models\Room;
use App\Models\Villa;
use App\Support\Availability;
use App\Support\PriceCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VillaShow extends Component
{
    public string $slug;

    public string $checkIn = '';

    public string $checkOut = '';

    public int $guests = 2;

    public bool $searched = false;

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        // Pastikan villa ada (404 jika tidak ditemukan / tidak aktif).
        $this->villa();
    }

    private function villa(): Villa
    {
        return Villa::query()
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->with(['images', 'amenities', 'rooms' => fn ($query) => $query->where('is_active', true)->orderBy('base_price')])
            ->firstOrFail();
    }

    public function search(): void
    {
        $this->validate([
            'checkIn' => ['required', 'date', 'after_or_equal:today'],
            'checkOut' => ['required', 'date', 'after:checkIn'],
            'guests' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $this->searched = true;
    }

    public function render(): View
    {
        $villa = $this->villa();
        $results = [];

        if ($this->searched) {
            foreach ($villa->rooms as $room) {
                /** @var Room $room */
                $quote = PriceCalculator::quote($room, $this->checkIn, $this->checkOut);

                $results[$room->getKey()] = [
                    'quote' => $quote,
                    'available' => Availability::isAvailable($room, $this->checkIn, $this->checkOut),
                    'fits' => $this->guests <= $room->max_guests,
                ];
            }
        }

        return view('livewire.site.villa-show', ['villa' => $villa, 'results' => $results]);
    }
}
