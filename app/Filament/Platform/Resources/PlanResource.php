<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\PlanResource\Pages\ManagePlans;
use App\Models\Plan;
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

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.plans');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.nav.plans');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('booking.fields.name'))
                ->required()
                ->maxLength(100),
            TextInput::make('slug')
                ->label(__('booking.fields.slug'))
                ->required()
                ->regex('/^[a-z0-9-]+$/')
                ->unique(ignoreRecord: true)
                ->maxLength(100),
            TextInput::make('price')
                ->label(__('booking.fields.price'))
                ->numeric()
                ->prefix('Rp')
                ->minValue(0)
                ->required(),
            TextInput::make('max_rooms')
                ->label(__('booking.fields.max_rooms'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->required(),
            Select::make('interval')
                ->label(__('booking.fields.interval'))
                ->options([
                    'month' => __('booking.intervals.month'),
                    'year' => __('booking.intervals.year'),
                ])
                ->default('month')
                ->required(),
            Toggle::make('is_active')
                ->label(__('booking.fields.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('booking.fields.name'))
                    ->searchable(),
                TextColumn::make('price')
                    ->label(__('booking.fields.price'))
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('max_rooms')
                    ->label(__('booking.fields.max_rooms'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('booking.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('max_rooms')
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
            'index' => ManagePlans::route('/'),
        ];
    }
}
