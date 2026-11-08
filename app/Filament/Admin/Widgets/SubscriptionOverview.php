<?php

namespace App\Filament\Admin\Widgets;

use App\Support\RoomQuota;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan paket langganan tenant yang sedang dibuka. */
class SubscriptionOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $subscription = $tenant?->currentSubscription();
        $plan = $subscription?->plan;

        return [
            Stat::make(__('booking.widgets.plan'), (string) ($plan?->name ?? '-')),
            Stat::make(
                __('booking.widgets.room_limit'),
                ($tenant ? RoomQuota::used($tenant) : 0).' / '.($plan?->max_rooms ?? 0)
            ),
            Stat::make(
                __('booking.widgets.valid_until'),
                $subscription?->ends_at?->format('d/m/Y') ?? '-'
            ),
        ];
    }
}
