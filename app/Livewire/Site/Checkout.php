<?php

namespace App\Livewire\Site;

use App\Exceptions\BookingRuleViolated;
use App\Exceptions\DatesUnavailable;
use App\Models\Room;
use App\Models\TenantSetting;
use App\Support\BookingService;
use App\Support\CurrentTenant;
use App\Support\PriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

class Checkout extends Component
{
    public int $roomId;

    public string $checkIn = '';

    public string $checkOut = '';

    public int $guests = 2;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $notes = '';

    public string $option = 'full';

    public function mount(int $roomId, string $checkIn, string $checkOut, int $guests = 2): void
    {
        $this->roomId = $roomId;
        $this->checkIn = $checkIn;
        $this->checkOut = $checkOut;
        $this->guests = $guests;

        $this->room(); // 404 bila kamar tidak ada / tidak aktif

        try {
            PriceCalculator::quote($this->room(), $checkIn, $checkOut);
        } catch (InvalidArgumentException|\Carbon\Exceptions\InvalidFormatException) {
            abort(404);
        }

        $this->option = $this->settings()?->payment_mode === 'dp' ? 'dp' : 'full';
    }

    private function room(): Room
    {
        return Room::query()
            ->where('is_active', true)
            ->whereHas('villa', fn ($query) => $query->where('is_active', true))
            ->with('villa')
            ->findOrFail($this->roomId);
    }

    private function settings(): ?TenantSetting
    {
        return TenantSetting::query()->where('tenant_id', app(CurrentTenant::class)->id())->first();
    }

    public function submit(): mixed
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'option' => ['required', 'in:full,dp'],
        ]);

        try {
            $booking = BookingService::create(
                $this->room(),
                $this->checkIn,
                $this->checkOut,
                $this->guests,
                ['name' => $this->name, 'email' => $this->email, 'phone' => $this->phone],
                $this->option,
                app()->getLocale(),
                'web',
                $this->notes !== '' ? $this->notes : null,
            );
        } catch (DatesUnavailable) {
            $this->addError('checkIn', __('site.errors.unavailable'));

            return null;
        } catch (BookingRuleViolated $e) {
            $this->addError('checkIn', $e->getMessage());

            return null;
        }

        return $this->redirect(route('site.booking', $booking->code), navigate: false);
    }

    public function render(): View
    {
        $room = $this->room();
        $quote = PriceCalculator::quote($room, $this->checkIn, $this->checkOut);
        $settings = $this->settings();
        $mode = $settings?->payment_mode ?? 'full';
        $percent = max(1, min(99, (int) ($settings?->dp_percent ?? 30)));
        $effective = $mode === 'both' ? $this->option : ($mode === 'dp' ? 'dp' : 'full');
        $dp = (int) ceil($quote['total'] * $percent / 100);
        $balanceDue = null;

        if ($effective === 'dp') {
            $days = (int) ($settings?->balance_due_days_before_checkin ?? 7);
            $balanceDue = CarbonImmutable::today()->max(CarbonImmutable::parse($this->checkIn)->subDays($days));
        }

        return view('livewire.site.checkout', [
            'room' => $room,
            'quote' => $quote,
            'mode' => $mode,
            'percent' => $percent,
            'payNow' => $effective === 'dp' ? $dp : $quote['total'],
            'balanceDue' => $balanceDue,
            'policy' => $settings?->cancellation_policy,
        ]);
    }
}
