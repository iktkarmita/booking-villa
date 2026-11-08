<?php

namespace App\Filament\Admin\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan paket langganan tenant yang sedang dibuka. */
class SubscriptionOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $subscription = Filament::getTenant()?->currentSubscription();
        $plan = $subscription?->plan;

        return [
            Stat::make(__('booking.widgets.plan'), (string) ($plan?->name ?? '-')),
            Stat::make(__('booking.widgets.room_limit'), (string) ($plan?->max_rooms ?? 0)),
            Stat::make(
                __('booking.widgets.valid_until'),
                $subscription?->ends_at?->format('d/m/Y') ?? '-'
            ),
        ];
    }
}
