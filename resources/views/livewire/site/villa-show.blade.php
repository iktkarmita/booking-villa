<div>
    <p><a href="{{ url('/') }}">&larr; {{ __('site.villa.back') }}</a></p>
    <h1>{{ $villa->localized('name') }}</h1>
    <p class="muted">{{ $villa->address }}</p>

    @if ($villa->images->isNotEmpty())
        <div class="photos">
            @foreach ($villa->images as $image)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="{{ $villa->localized('name') }}">
            @endforeach
        </div>
    @endif

    @if ($villa->localized('description'))
        <p style="white-space:pre-line;">{{ $villa->localized('description') }}</p>
    @endif

    @if ($villa->amenities->isNotEmpty())
        <h2>{{ __('site.villa.amenities') }}</h2>
        <div class="tags">
            @foreach ($villa->amenities as $amenity)
                <span>{{ $amenity->localized('name') }}</span>
            @endforeach
        </div>
    @endif

    <h2>{{ __('site.villa.rooms') }}</h2>

    <form wire:submit="search" class="card" style="margin-bottom:1rem;">
        <div class="body">
            <div class="row">
                <div class="field">
                    <label for="checkIn">{{ __('site.villa.check_in') }}</label>
                    <input id="checkIn" type="date" wire:model="checkIn" min="{{ now()->toDateString() }}">
                    @error('checkIn') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="checkOut">{{ __('site.villa.check_out') }}</label>
                    <input id="checkOut" type="date" wire:model="checkOut" min="{{ now()->toDateString() }}">
                    @error('checkOut') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="field" style="max-width:7rem;">
                    <label for="guests">{{ __('site.villa.guests') }}</label>
                    <input id="guests" type="number" min="1" wire:model="guests">
                    @error('guests') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="field" style="flex:0;">
                    <button class="btn" type="submit">{{ __('site.villa.search') }}</button>
                </div>
            </div>
        </div>
    </form>

    @unless ($searched)
        <p class="muted">{{ __('site.villa.hint') }}</p>
    @endunless

    <div class="grid" style="grid-template-columns:1fr;">
        @foreach ($villa->rooms as $room)
            @php
                $result = $results[$room->getKey()] ?? null;
                $bookable = $result && $result['available'] && $result['fits'] && $result['quote']['meets_min_nights'];
            @endphp
            <div class="card">
                <div class="body">
                    <div class="row" style="justify-content:space-between;">
                        <div>
                            <strong>{{ $room->localized('name') }}</strong>
                            <div class="muted">
                                {{ __('site.villa.bedrooms', ['count' => $room->bedrooms]) }} ·
                                {{ __('site.villa.capacity', ['count' => $room->max_guests]) }}
                                @if ($room->min_nights > 1)
                                    · {{ __('site.villa.min_nights', ['count' => $room->min_nights]) }}
                                @endif
                            </div>
                        </div>
                        <div style="text-align:right;">
                            @if ($result)
                                <div class="price">{{ \App\Support\Money::idr($result['quote']['total']) }}</div>
                                <div class="muted">{{ __('site.villa.nights', ['count' => $result['quote']['nights']]) }}</div>
                            @else
                                <div class="price">{{ \App\Support\Money::idr($room->base_price) }}</div>
                                <div class="muted">{{ __('site.home.per_night') }}</div>
                            @endif
                        </div>
                    </div>

                    @if ($result)
                        <div style="margin-top:.75rem;">
                            @if (! $result['available'])
                                <span class="err">{{ __('site.villa.unavailable') }}</span>
                            @elseif (! $result['fits'])
                                <span class="err">{{ __('site.villa.over_capacity') }}</span>
                            @elseif (! $result['quote']['meets_min_nights'])
                                <span class="err">{{ __('site.errors.min_nights', ['min' => $result['quote']['min_nights']]) }}</span>
                            @else
                                <span class="ok">{{ __('site.villa.available') }}</span>
                            @endif

                            @if ($bookable)
                                <a class="btn" style="float:right;" href="{{ route('site.checkout', ['room' => $room->getKey(), 'check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => $guests]) }}">{{ __('site.villa.book') }}</a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
