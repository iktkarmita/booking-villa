<div>
    <p><a href="{{ route('site.villa', $room->villa->slug) }}">&larr; {{ $room->villa->localized('name') }}</a></p>
    <h1>{{ __('site.checkout.title') }}</h1>

    <div class="card" style="margin-bottom:1rem;">
        <div class="body">
            <strong>{{ $room->villa->localized('name') }} - {{ $room->localized('name') }}</strong>
            <div class="muted">
                {{ \Carbon\Carbon::parse($checkIn)->translatedFormat('d M Y') }} &rarr;
                {{ \Carbon\Carbon::parse($checkOut)->translatedFormat('d M Y') }}
                · {{ __('site.villa.nights', ['count' => $quote['nights']]) }}
                · {{ __('site.villa.guests') }}: {{ $guests }}
            </div>
            <div style="margin-top:.5rem;">
                {{ __('site.checkout.total') }}: <span class="price">{{ \App\Support\Money::idr($quote['total']) }}</span>
            </div>
            @error('checkIn') <div class="err">{{ $message }}</div> @enderror
        </div>
    </div>

    <form wire:submit="submit" class="card">
        <div class="body">
            <div class="field">
                <label for="name">{{ __('site.checkout.name') }}</label>
                <input id="name" type="text" wire:model="name" autocomplete="name">
                @error('name') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="row">
                <div class="field">
                    <label for="email">{{ __('site.checkout.email') }}</label>
                    <input id="email" type="email" wire:model="email" autocomplete="email">
                    @error('email') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="phone">{{ __('site.checkout.phone') }}</label>
                    <input id="phone" type="tel" wire:model="phone" autocomplete="tel">
                    @error('phone') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="field">
                <label for="notes">{{ __('site.checkout.notes') }}</label>
                <textarea id="notes" rows="2" wire:model="notes"></textarea>
                @error('notes') <div class="err">{{ $message }}</div> @enderror
            </div>

            @if ($mode === 'both')
                <div class="field">
                    <label for="option">{{ __('site.checkout.option') }}</label>
                    <select id="option" wire:model.live="option">
                        <option value="full">{{ __('site.checkout.option_full') }}</option>
                        <option value="dp">{{ __('site.checkout.option_dp', ['percent' => $percent]) }}</option>
                    </select>
                    @error('option') <div class="err">{{ $message }}</div> @enderror
                </div>
            @endif

            <p>
                {{ __('site.checkout.pay_now') }}: <span class="price">{{ \App\Support\Money::idr($payNow) }}</span>
                @if ($balanceDue)
                    <br><span class="muted">{{ __('site.checkout.dp_note', ['date' => $balanceDue->translatedFormat('d M Y')]) }}</span>
                @endif
            </p>

            @if ($policy && filled($policy[app()->getLocale()] ?? ($policy['id'] ?? null)))
                <details style="margin-bottom:1rem;">
                    <summary>{{ __('site.checkout.policy') }}</summary>
                    <p class="muted" style="white-space:pre-line;">{{ $policy[app()->getLocale()] ?? $policy['id'] }}</p>
                </details>
            @endif

            <button class="btn" type="submit" wire:loading.attr="disabled">{{ __('site.checkout.submit') }}</button>
        </div>
    </form>
</div>
