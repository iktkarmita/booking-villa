<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SeasonalPriceResource\Pages\ManageSeasonalPrices;
use App\Models\Room;
use App\Models\SeasonalPrice;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeasonalPriceResource extends Resource
{
    protected static ?string $model = SeasonalPrice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('calendar.nav.seasonal_prices');
    }

    public static function getModelLabel(): string
    {
        return __('calendar.models.seasonal_price');
    }

    public static function getPluralModelLabel(): string
    {
        return __('calendar.nav.seasonal_prices');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('room_id')
                ->label(__('calendar.fields.room'))
                ->options(fn () => Room::query()->with('villa')->get()->mapWithKeys(
                    fn (Room $room) => [$room->getKey() => $room->villa?->localized('name').' - '.$room->localized('name')]
                ))
                ->required()
                ->searchable()
                ->columnSpanFull(),
            TextInput::make('label')
                ->label(__('calendar.fields.label'))
                ->required()
                ->maxLength(100)
                ->columnSpanFull(),
            DatePicker::make('start_date')
                ->label(__('calendar.fields.start_date'))
                ->required()
                ->native(false),
            DatePicker::make('end_date')
                ->label(__('calendar.fields.end_date'))
                ->required()
                ->native(false)
                ->afterOrEqual('start_date'),
            TextInput::make('price_per_night')
                ->label(__('calendar.fields.price_per_night'))
                ->numeric()->prefix('Rp')->minValue(0)->required(),
            TextInput::make('min_nights')
                ->label(__('calendar.fields.min_nights'))
                ->helperText(__('calendar.fields.min_nights_help'))
                ->numeric()->integer()->minValue(1)->maxValue(60),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room_name')
                    ->label(__('calendar.fields.room'))
                    ->state(fn (SeasonalPrice $record) => $record->room?->localized('name')),
                TextColumn::make('label')
                    ->label(__('calendar.fields.label'))
                    ->searchable(),
                TextColumn::make('start_date')
                    ->label(__('calendar.fields.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('calendar.fields.end_date'))
                    ->date('d/m/Y'),
                TextColumn::make('price_per_night')
                    ->label(__('calendar.fields.price_per_night'))
                    ->money('IDR', locale: 'id'),
                TextColumn::make('min_nights')
                    ->label(__('calendar.fields.min_nights'))
                    ->placeholder('-'),
            ])
            ->defaultSort('start_date', 'desc')
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
            'index' => ManageSeasonalPrices::route('/'),
        ];
    }
}
