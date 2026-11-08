<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\SubscriptionInvoiceResource\Pages\ManageSubscriptionInvoices;
use App\Models\SubscriptionInvoice;
use App\Support\Money;
use App\Support\SubscriptionService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Semua tagihan langganan: super admin menandai lunas (perpanjang + aktifkan tenant) atau membatalkan. */
class SubscriptionInvoiceResource extends Resource
{
    protected static ?string $model = SubscriptionInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('billing.platform_nav');
    }

    public static function getModelLabel(): string
    {
        return __('billing.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing.platform_nav');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function table(Table $table): Table
    {
        $statuses = collect(['unpaid', 'paid', 'void'])->mapWithKeys(fn ($s) => [$s => __('billing.status.'.$s)])->all();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('subscription.tenant'))
            ->columns([
                TextColumn::make('number')->label(__('billing.fields.number'))->searchable(),
                TextColumn::make('subscription.tenant.name')->label(__('billing.fields.tenant')),
                TextColumn::make('amount')->label(__('billing.fields.amount'))->formatStateUsing(fn ($state) => Money::idr((int) $state)),
                TextColumn::make('period_start')
                    ->label(__('billing.fields.period'))
                    ->state(fn (SubscriptionInvoice $r) => $r->period_start->format('d/m/Y').' - '.$r->period_end->format('d/m/Y')),
                TextColumn::make('due_date')->label(__('billing.fields.due_date'))->date('d/m/Y')->sortable(),
                TextColumn::make('status')
                    ->label(__('billing.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $statuses[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('billing.fields.status'))->options($statuses)->default('unpaid'),
            ])
            ->recordActions([
                Action::make('mark_paid')
                    ->label(__('billing.actions.mark_paid'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SubscriptionInvoice $r) => $r->status === 'unpaid')
                    ->requiresConfirmation()
                    ->schema([Textarea::make('note')->label(__('billing.fields.note'))->rows(2)->maxLength(250)])
                    ->action(fn (SubscriptionInvoice $r, array $data) => self::run(fn () => SubscriptionService::markPaid($r, $data['note'] ?? null))),
                Action::make('void')
                    ->label(__('billing.actions.void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SubscriptionInvoice $r) => $r->status === 'unpaid')
                    ->requiresConfirmation()
                    ->action(fn (SubscriptionInvoice $r) => self::run(fn () => SubscriptionService::void($r))),
            ]);
    }

    private static function run(callable $callback): void
    {
        try {
            $callback();
            Notification::make()->title(__('billing.actions.done'))->success()->send();
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptionInvoices::route('/'),
        ];
    }
}
