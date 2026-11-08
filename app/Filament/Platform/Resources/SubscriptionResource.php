<?php

namespace App\Filament\Platform\Resources;

use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\SubscriptionResource\Pages\ManageSubscriptions;
use App\Models\Subscription;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.subscriptions');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.nav.subscriptions');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tenant_id')
                ->label(__('booking.fields.tenant'))
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('plan_id')
                ->label(__('booking.fields.plan'))
                ->relationship('plan', 'name')
                ->preload()
                ->required(),
            Select::make('status')
                ->label(__('booking.fields.status'))
                ->options(SubscriptionStatus::class)
                ->default(SubscriptionStatus::Trial->value)
                ->required(),
            DatePicker::make('starts_at')
                ->label(__('booking.fields.starts_at'))
                ->default(now())
                ->required(),
            DatePicker::make('ends_at')
                ->label(__('booking.fields.ends_at')),
            DatePicker::make('grace_until')
                ->label(__('booking.fields.grace_until')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->label(__('booking.fields.tenant'))
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label(__('booking.fields.plan')),
                TextColumn::make('status')
                    ->label(__('booking.fields.status'))
                    ->badge(),
                TextColumn::make('starts_at')
                    ->label(__('booking.fields.starts_at'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('booking.fields.ends_at'))
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptions::route('/'),
        ];
    }
}
