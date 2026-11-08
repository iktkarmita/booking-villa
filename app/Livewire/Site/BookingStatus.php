<?php

namespace App\Livewire\Site;

use App\Enums\BookingStatus as Status;
use App\Exceptions\BookingRuleViolated;
use App\Models\Booking;
use App\Models\TenantSetting;
use App\Support\Payment\GatewayFactory;
use App\Support\PaymentService;
use App\Support\QrImage;
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

    public function payBalance(): void
    {
        try {
            PaymentService::requestBalance($this->booking());
        } catch (BookingRuleViolated $e) {
            $this->addError('balance', $e->getMessage());
        }
    }

    public function render(): View
    {
        $booking = $this->booking();
        $settings = TenantSetting::query()->where('tenant_id', $booking->tenant_id)->first();
        $pending = $booking->pendingPayment();
        $waNumber = preg_replace('/\D+/', '', (string) $settings?->wa_number);

        $isQris = $pending?->method === 'qris' && filled($pending->qr_string);
        $canPayBalance = $pending === null
            && in_array($booking->status, [Status::Confirmed, Status::CheckedIn], true)
            && $booking->remaining() > 0
            && GatewayFactory::forSettings($settings) !== null;

        return view('livewire.site.booking-status', [
            'isQris' => $isQris,
            'qrImage' => $isQris ? QrImage::dataUri($pending->qr_string) : null,
            'canPayBalance' => $canPayBalance,
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
