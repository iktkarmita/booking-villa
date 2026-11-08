<x-filament-panels::page>
    @php($options = $this->roomOptions())

    @if ($options->isEmpty())
        <x-filament::section>
            {{ __('calendar.page.no_rooms') }}
        </x-filament::section>
    @else
        <x-filament::section>
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;">
                <div style="min-width:16rem;">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="roomId">
                            @foreach ($options as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div style="display:flex;gap:.5rem;align-items:center;">
                    <x-filament::button color="gray" wire:click="previousMonth" title="{{ __('calendar.page.prev') }}">&larr;</x-filament::button>
                    <strong style="min-width:9rem;text-align:center;">{{ $this->monthLabel() }}</strong>
                    <x-filament::button color="gray" wire:click="nextMonth" title="{{ __('calendar.page.next') }}">&rarr;</x-filament::button>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px;margin-top:1rem;">
                @foreach (__('calendar.page.weekdays') as $weekday)
                    <div style="text-align:center;font-size:.75rem;opacity:.7;padding:.25rem 0;">{{ $weekday }}</div>
                @endforeach

                @foreach ($this->cells() as $cell)
                    @if ($cell === null)
                        <div></div>
                    @else
                        @php
                            $bg = match ($cell['status']) {
                                'booking' => 'rgba(239,68,68,.25)',
                                'block' => 'rgba(156,163,175,.35)',
                                default => 'rgba(34,197,94,.15)',
                            };
                        @endphp
                        <div title="{{ $cell['note'] }}" style="background:{{ $bg }};border-radius:6px;padding:.4rem .5rem;min-height:3.5rem;">
                            <div style="font-weight:600;">{{ $cell['day'] }}</div>
                            <div style="font-size:.7rem;opacity:.75;">
                                @if ($cell['status'] === 'free')
                                    {{ number_format($cell['price'] / 1000, 0, ',', '.') }}k
                                @elseif ($cell['status'] === 'booking')
                                    {{ __('calendar.page.booked') }}
                                @else
                                    {{ __('calendar.page.blocked') }}
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div style="display:flex;gap:1rem;margin-top:1rem;font-size:.8rem;flex-wrap:wrap;">
                <span><span style="display:inline-block;width:.8rem;height:.8rem;background:rgba(34,197,94,.3);border-radius:3px;"></span> {{ __('calendar.page.free') }}</span>
                <span><span style="display:inline-block;width:.8rem;height:.8rem;background:rgba(239,68,68,.4);border-radius:3px;"></span> {{ __('calendar.page.booked') }}</span>
                <span><span style="display:inline-block;width:.8rem;height:.8rem;background:rgba(156,163,175,.5);border-radius:3px;"></span> {{ __('calendar.page.blocked') }}</span>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
