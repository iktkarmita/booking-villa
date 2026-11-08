<div>
    @if ($villas->isEmpty())
        <p class="muted">{{ __('site.home.empty') }}</p>
    @else
        <div class="grid">
            @foreach ($villas as $villa)
                @php
                    $cover = $villa->images->first();
                    $min = $villa->rooms->min('base_price');
                @endphp
                <a class="card" href="{{ route('site.villa', $villa->slug) }}" style="color:inherit;text-decoration:none;">
                    @if ($cover)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($cover->path) }}" alt="{{ $villa->localized('name') }}">
                    @else
                        <img alt="" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='4' height='3'%3E%3Crect width='4' height='3' fill='%23e5e7eb'/%3E%3C/svg%3E">
                    @endif
                    <div class="body">
                        <strong>{{ $villa->localized('name') }}</strong>
                        <div class="muted">{{ $villa->address }}</div>
                        <div style="margin-top:.5rem;">
                            <span class="muted">{{ __('site.home.from') }}</span>
                            <span class="price">{{ \App\Support\Money::idr($min) }}</span>
                            <span class="muted">{{ __('site.home.per_night') }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
