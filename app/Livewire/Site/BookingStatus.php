<?php

namespace App\Livewire\Site;

use App\Models\Booking;
use App\Models\TenantSetting;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class BookingStatus extends Component
{
    use WithFileUploads;

    public string $code;

    public $proof = null;

    public bool $uploaded = false;

    public function mount(string $code): void
    {
        $this->code = $code;
        $this->booking(); // 404 bila kode tidak dikenal
    }

    private function booking(): Booking
    {
        return Booking::query()
            ->where('code', $this->code)
            ->with(['room.villa', 'guest', 'payments'])
            ->firstOrFail();
    }

    public function uploadProof(): void
    {
        $this->validate(['proof' => ['required', 'image', 'max:4096']]);

        $booking = $this->booking();
        $payment = $booking->pendingPayment();

        if ($payment === null) {
            return;
        }

        $payment->update(['proof_path' => $this->proof->store('payment-proofs', 'public')]);

        $this->reset('proof');
        $this->uploaded = true;
    }

    public function render(): View
    {
        $booking = $this->booking();
        $settings = TenantSetting::query()->where('tenant_id', $booking->tenant_id)->first();
        $pending = $booking->pendingPayment();
        $waNumber = preg_replace('/\D+/', '', (string) $settings?->wa_number);

        return view('livewire.site.booking-status', [
            'booking' => $booking,
            'settings' => $settings,
            'pending' => $pending,
            'hasProof' => $pending?->proof_path !== null,
            'waLink' => $waNumber !== ''
                ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode(__('site.booking.wa_text', ['code' => $booking->code]))
                : null,
        ]);
    }
}
