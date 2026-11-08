<div wire:poll.30s>
    <h1>{{ __('site.booking.title', ['code' => $booking->code]) }}</h1>
    <p class="muted">{{ __('site.booking.save_code') }}</p>

    <div class="card" style="margin-bottom:1rem;">
        <div class="body">
            <table class="info">
                <tr><td>{{ __('site.booking.status') }}</td><td><span class="badge {{ $booking->status->color() }}">{{ $booking->status->label() }}</span></td></tr>
                <tr><td>{{ __('site.booking.payment') }}</td><td><span class="badge {{ $booking->payment_status->color() }}">{{ $booking->payment_status->label() }}</span></td></tr>
                <tr><td>{{ __('site.booking.dates') }}</td><td>{{ $booking->check_in->translatedFormat('d M Y') }} &rarr; {{ $booking->check_out->translatedFormat('d M Y') }} ({{ __('site.villa.nights', ['count' => $booking->nights]) }})</td></tr>
                <tr><td>{{ __('site.villa.rooms') }}</td><td>{{ $booking->room->villa->localized('name') }} - {{ $booking->room->localized('name') }}</td></tr>
                <tr><td>{{ __('site.booking.guest') }}</td><td>{{ $booking->guest->name }} · {{ $booking->guests_count }} {{ __('site.villa.guests') }}</td></tr>
                <tr><td>{{ __('site.booking.total') }}</td><td class="price">{{ \App\Support\Money::idr($booking->total) }}</td></tr>
                <tr><td>{{ __('site.booking.paid') }}</td><td>{{ \App\Support\Money::idr($booking->paid_amount) }}</td></tr>
                <tr><td>{{ __('site.booking.remaining') }}</td><td>{{ \App\Support\Money::idr($booking->remaining()) }}</td></tr>
            </table>
            @if ($booking->balance_due_date && $booking->remaining() > 0)
                <p class="muted">{{ __('site.booking.balance_due', ['date' => $booking->balance_due_date->translatedFormat('d M Y')]) }}</p>
            @endif
        </div>
    </div>

    @if ($pending)
        <div class="card" style="margin-bottom:1rem;">
            <div class="body">
                <h2 style="margin-top:0;">{{ __('site.booking.pay_title') }}</h2>
                @unless ($isQris)
                    <p>{{ __('site.booking.pay_info', ['amount' => \App\Support\Money::idr($pending->amount)]) }}</p>
                @endunless

                @if ($isQris)
                    <p>{{ __('site.booking.qris_info', ['amount' => \App\Support\Money::idr($pending->amount)]) }}</p>
                    @if ($qrImage)
                        <p style="text-align:center;"><img src="{{ $qrImage }}" alt="QRIS" style="width:240px;max-width:100%;background:#fff;padding:8px;border:1px solid #e5e7eb;border-radius:8px;"></p>
                    @else
                        <p><code style="word-break:break-all;">{{ $pending->qr_string }}</code></p>
                    @endif
                    <p class="muted">{{ __('site.booking.qris_waiting') }}</p>
                @else
                @if (filled($settings?->bank_account_number))
                    <table class="info">
                        <tr><td>{{ __('site.booking.bank') }}</td><td><strong>{{ $settings->bank_name }}</strong></td></tr>
                        <tr><td>{{ __('site.booking.account_number') }}</td><td><strong>{{ $settings->bank_account_number }}</strong></td></tr>
                        <tr><td>{{ __('site.booking.account_name') }}</td><td><strong>{{ $settings->bank_account_name }}</strong></td></tr>
                    </table>
                @else
                    <p class="err">{{ __('site.booking.no_bank') }}</p>
                @endif

                @endif

                @if ($pending->expires_at)
                    <p class="muted">{{ __('site.booking.deadline', ['time' => $pending->expires_at->timezone(config('app.timezone'))->translatedFormat('d M Y H:i')]) }}</p>
                @endif

                @if ($isQris)
                    {{-- QRIS: konfirmasi otomatis lewat callback gateway --}}
                @elseif ($hasProof || $uploaded)
                    <p class="ok">{{ __('site.booking.uploaded') }}</p>
                @else
                    <form wire:submit="uploadProof">
                        <div class="field">
                            <label for="proof">{{ __('site.booking.upload') }}</label>
                            <input id="proof" type="file" wire:model="proof" accept="image/*">
                            @error('proof') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <button class="btn" type="submit" wire:loading.attr="disabled">{{ __('site.booking.upload_btn') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if ($canPayBalance)
        <div class="card" style="margin-bottom:1rem;">
            <div class="body">
                <button class="btn" type="button" wire:click="payBalance" wire:loading.attr="disabled">{{ __('site.booking.pay_balance', ['amount' => \App\Support\Money::idr($booking->remaining())]) }}</button>
                @error('balance') <div class="err">{{ $message }}</div> @enderror
            </div>
        </div>
    @endif

    @if ($refund)
        <div class="card" style="margin-bottom:1rem;">
            <div class="body">
                @if ($refund->status === 'requested')
                    <p>{{ __('site.booking.refund_requested') }}</p>
                @elseif ($refund->status === 'approved')
                    <p>{{ __('site.booking.refund_approved', ['amount' => \App\Support\Money::idr((int) $refund->amount_approved)]) }}</p>
                @elseif ($refund->status === 'refunded')
                    <p class="ok">{{ __('site.booking.refund_refunded', ['amount' => \App\Support\Money::idr((int) $refund->amount_approved)]) }}</p>
                @elseif ($refund->status === 'rejected')
                    <p class="err">{{ __('site.booking.refund_rejected') }}</p>
                    @if (filled($refund->owner_note))<p class="muted">{{ $refund->owner_note }}</p>@endif
                @endif
            </div>
        </div>
    @endif

    @if ($canCancel)
        <div class="card" style="margin-bottom:1rem;">
            <div class="body">
                @if (! $cancelOpen)
                    <button class="btn gray" type="button" wire:click="$set('cancelOpen', true)">{{ __('site.booking.cancel_open') }}</button>
                @else
                    <h2 style="margin-top:0;">{{ __('site.booking.cancel_title') }}</h2>
                    <p class="muted">{{ __('site.booking.cancel_estimate', ['percent' => $refundPercent, 'amount' => \App\Support\Money::idr($refundEstimate)]) }}</p>
                    <form wire:submit="requestCancel">
                        <div class="field">
                            <label for="cancelReason">{{ __('site.booking.cancel_reason') }}</label>
                            <textarea id="cancelReason" rows="2" wire:model="cancelReason"></textarea>
                            @error('cancelReason') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="destBank">{{ __('site.booking.cancel_bank') }}</label>
                            <input id="destBank" type="text" wire:model="destBank">
                            @error('destBank') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="destName">{{ __('site.booking.cancel_name') }}</label>
                            <input id="destName" type="text" wire:model="destName">
                            @error('destName') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label for="destNumber">{{ __('site.booking.cancel_number') }}</label>
                            <input id="destNumber" type="text" wire:model="destNumber">
                            @error('destNumber') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        @error('cancel') <div class="err">{{ $message }}</div> @enderror
                        <button class="btn" type="submit" wire:loading.attr="disabled">{{ __('site.booking.cancel_btn') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if ($waLink)
        <p><a class="btn gray" href="{{ $waLink }}" target="_blank" rel="noopener">{{ __('site.booking.contact') }}</a></p>
    @endif
</div>
