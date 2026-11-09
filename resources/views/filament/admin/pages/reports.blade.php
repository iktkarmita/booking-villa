<x-filament-panels::page>
    @php
        $s = $this->summary();
        $idr = fn ($n) => \App\Support\Money::idr($n);
    @endphp

    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="font-size:.8rem;opacity:.7;">{{ __('report.from') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="from" />
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;">
                <span style="font-size:.8rem;opacity:.7;">{{ __('report.to') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="to" />
                </x-filament::input.wrapper>
            </label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <x-filament::button color="gray" size="sm" wire:click="setPreset('month')">{{ __('report.presets.month') }}</x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="setPreset('last_month')">{{ __('report.presets.last_month') }}</x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="setPreset('last_30')">{{ __('report.presets.last_30') }}</x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="setPreset('year')">{{ __('report.presets.year') }}</x-filament::button>
            </div>
        </div>
        <p style="margin-top:.75rem;font-size:.8rem;opacity:.7;">
            {{ __('report.period', ['from' => $s['from'], 'to' => $s['to'], 'days' => $s['days']]) }}
        </p>
    </x-filament::section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:1rem;">
        @foreach ([
            ['report.cards.revenue', $idr($s['revenue'])],
            ['report.cards.refunded', $idr($s['refunded'])],
            ['report.cards.net', $idr($s['net'])],
            ['report.cards.bookings', number_format($s['bookings_total'], 0, ',', '.')],
            ['report.cards.occupancy', number_format($s['occupancy'], 1, ',', '.').'%'],
            ['report.cards.nights', number_format($s['nights_sold'], 0, ',', '.').' / '.number_format($s['nights_capacity'], 0, ',', '.')],
        ] as [$label, $value])
            <x-filament::section>
                <div style="font-size:.8rem;opacity:.7;">{{ __($label) }}</div>
                <div style="font-size:1.5rem;font-weight:700;margin-top:.25rem;">{{ $value }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section :heading="__('report.by_status')">
        @if (empty($s['bookings']))
            <p style="opacity:.7;">{{ __('report.empty') }}</p>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                @foreach ($s['bookings'] as $status => $total)
                    <x-filament::badge :color="\App\Enums\BookingStatus::from($status)->color()">
                        {{ \App\Enums\BookingStatus::from($status)->label() }}: {{ $total }}
                    </x-filament::badge>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('report.by_villa')">
        @if (empty($s['villas']))
            <p style="opacity:.7;">{{ __('report.empty') }}</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.3);">
                            <th style="padding:.5rem;">{{ __('report.cols.villa') }}</th>
                            <th style="padding:.5rem;text-align:right;">{{ __('report.cols.bookings') }}</th>
                            <th style="padding:.5rem;text-align:right;">{{ __('report.cols.nights') }}</th>
                            <th style="padding:.5rem;text-align:right;">{{ __('report.cols.occupancy') }}</th>
                            <th style="padding:.5rem;text-align:right;">{{ __('report.cols.revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s['villas'] as $villa)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
                                <td style="padding:.5rem;">{{ $villa['name'] }}</td>
                                <td style="padding:.5rem;text-align:right;">{{ $villa['bookings'] }}</td>
                                <td style="padding:.5rem;text-align:right;">{{ $villa['nights'] }} / {{ $villa['capacity'] }}</td>
                                <td style="padding:.5rem;text-align:right;">{{ number_format($villa['occupancy'], 1, ',', '.') }}%</td>
                                <td style="padding:.5rem;text-align:right;">{{ $idr($villa['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
