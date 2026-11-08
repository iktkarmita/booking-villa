<?php

namespace App\Filament\Platform\Resources;

use App\Enums\TenantStatus;
use App\Filament\Platform\Resources\TenantResource\Pages\ManageTenants;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.tenants');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.tenant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.nav.tenants');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('booking.fields.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label(__('booking.fields.slug'))
                ->required()
                ->regex('/^[a-z0-9-]+$/')
                ->unique(ignoreRecord: true)
                ->maxLength(63),
            TextInput::make('subdomain')
                ->label(__('booking.fields.subdomain'))
                ->helperText(__('booking.help.subdomain'))
                ->regex('/^[a-z0-9-]+$/')
                ->unique(ignoreRecord: true)
                ->maxLength(63),
            TextInput::make('custom_domain')
                ->label(__('booking.fields.custom_domain'))
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            Select::make('status')
                ->label(__('booking.fields.status'))
                ->options(TenantStatus::class)
                ->default(TenantStatus::Trial->value)
                ->required(),
            Select::make('default_locale')
                ->label(__('booking.fields.default_locale'))
                ->options(['id' => 'Bahasa Indonesia', 'en' => 'English'])
                ->default('id')
                ->required(),
            TextInput::make('timezone')
                ->label(__('booking.fields.timezone'))
                ->default('Asia/Makassar')
                ->required(),
            TextInput::make('currency')
                ->label(__('booking.fields.currency'))
                ->default('IDR')
                ->required()
                ->maxLength(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('booking.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subdomain')
                    ->label(__('booking.fields.subdomain'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('booking.fields.status'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('suspend')
                    ->label(__('billing.actions.suspend'))
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record) => ! $record->isSuspended())
                    ->action(fn (Tenant $record) => $record->update(['status' => TenantStatus::Suspended])),
                Action::make('activate')
                    ->label(__('billing.actions.activate'))
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record) => $record->isSuspended())
                    ->action(fn (Tenant $record) => $record->update(['status' => TenantStatus::Active])),
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
            'index' => ManageTenants::route('/'),
        ];
    }
}
