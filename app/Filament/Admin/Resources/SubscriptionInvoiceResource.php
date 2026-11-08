<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SubscriptionInvoiceResource\Pages\ManageSubscriptionInvoices;
use App\Models\SubscriptionInvoice;
use App\Support\Money;
use App\Support\PlatformNotifier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Tagihan langganan milik tenant (hanya baca). Pembayaran dikonfirmasi admin platform. */
class SubscriptionInvoiceResource extends Resource
{
    protected static ?string $model = SubscriptionInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('billing.nav');
    }

    public static function getModelLabel(): string
    {
        return __('billing.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing.nav');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label(__('billing.fields.number'))->searchable(),
                TextColumn::make('amount')->label(__('billing.fields.amount'))->formatStateUsing(fn ($state) => Money::idr((int) $state)),
                TextColumn::make('period_start')
                    ->label(__('billing.fields.period'))
                    ->state(fn (SubscriptionInvoice $r) => $r->period_start->format('d/m/Y').' - '.$r->period_end->format('d/m/Y')),
                TextColumn::make('due_date')->label(__('billing.fields.due_date'))->date('d/m/Y'),
                TextColumn::make('status')
                    ->label(__('billing.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('billing.status.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->description(__('billing.pay_instructions', ['bank' => PlatformNotifier::bankLine()]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptionInvoices::route('/'),
        ];
    }
}
