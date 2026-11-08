<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\UserResource\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.users');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.nav.users');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('booking.fields.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('booking.fields.email'))
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('password')
                ->label(__('booking.fields.password'))
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state)),
            Toggle::make('is_super_admin')
                ->label(__('booking.fields.is_super_admin'))
                ->default(false),
            Select::make('tenants')
                ->label(__('booking.fields.tenants'))
                ->relationship('tenants', 'name')
                ->multiple()
                ->preload(),
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
                TextColumn::make('email')
                    ->label(__('booking.fields.email'))
                    ->searchable(),
                IconColumn::make('is_super_admin')
                    ->label(__('booking.fields.is_super_admin'))
                    ->boolean(),
                TextColumn::make('tenants.name')
                    ->label(__('booking.fields.tenants'))
                    ->badge(),
            ])
            ->defaultSort('name')
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
            'index' => ManageUsers::route('/'),
        ];
    }
}
