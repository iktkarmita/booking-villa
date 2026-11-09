<?php

namespace App\Livewire\Site;

use App\Enums\BookingStatus as Status;
use App\Exceptions\BookingRuleViolated;
use App\Models\Booking;
use App\Models\TenantSetting;
use App\Support\Payment\GatewayFactory;
use App\Support\PaymentService;
use App\Support\RefundPolicy;
use App\Support\RefundService;
use App\Support\QrImage;
use App\Livewire\Concerns\ThrottlesActions;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class BookingStatus extends Component
{
    use ThrottlesActions, WithFileUploads;

    public string $code;

    public $proof = null;

    public bool $uploaded = false;

    public bool $cancelOpen = false;

    public string $cancelReason = '';

    public string $destBank = '';

    public string $destName = '';

    public string $destNumber = '';

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
        if ($this->throttled('proof', 10, 'proof')) {
            return;
        }

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

    public function requestCancel(): void
    {
        if ($this->throttled('cancel', 5, 'cancel')) {
            return;
        }

        $this->validate([
            'cancelReason' => ['required', 'string', 'max:500'],
            'destBank' => ['required', 'string', 'max:100'],
            'destName' => ['required', 'string', 'max:100'],
            'destNumber' => ['required', 'string', 'max:50'],
        ]);

        try {
            RefundService::request($this->booking(), $this->cancelReason, [
                'bank' => $this->destBank,
                'name' => $this->destName,
                'number' => $this->destNumber,
            ]);
        } catch (BookingRuleViolated $e) {
            $this->addError('cancel', $e->getMessage());

            return;
        }

        $this->reset('cancelOpen', 'cancelReason', 'destBank', 'destName', 'destNumber');
    }

    public function payBalance(): void
    {
        if ($this->throttled('balance', 6, 'balance')) {
            return;
        }

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

        $refund = $booking->refunds()->latest('id')->first();
        $canCancel = $booking->status === Status::Confirmed
            && $booking->paid_amount > 0
            && ! in_array($refund?->status, ['requested', 'approved'], true);
        $refundPercent = RefundPolicy::percent($settings, $booking->check_in);

        return view('livewire.site.booking-status', [
            'refund' => $refund,
            'canCancel' => $canCancel,
            'refundPercent' => $refundPercent,
            'refundEstimate' => (int) floor($booking->paid_amount * $refundPercent / 100),
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
