<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RoomResource\Pages\ManageRooms;
use App\Models\Room;
use App\Models\Villa;
use App\Support\RoomQuota;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('villa.nav.rooms');
    }

    public static function getModelLabel(): string
    {
        return __('villa.models.room');
    }

    public static function getPluralModelLabel(): string
    {
        return __('villa.nav.rooms');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('villa_id')
                ->label(__('villa.fields.villa'))
                ->options(fn () => Villa::query()->get()->mapWithKeys(
                    fn (Villa $villa) => [$villa->getKey() => $villa->localized('name')]
                ))
                ->required()
                ->searchable()
                ->columnSpanFull(),
            TextInput::make('name.id')
                ->label(__('villa.fields.name_id'))
                ->required()
                ->maxLength(150),
            TextInput::make('name.en')
                ->label(__('villa.fields.name_en'))
                ->maxLength(150),
            TextInput::make('bedrooms')
                ->label(__('villa.fields.bedrooms'))
                ->numeric()->integer()->minValue(0)->maxValue(50)->default(1)->required(),
            TextInput::make('bathrooms')
                ->label(__('villa.fields.bathrooms'))
                ->numeric()->integer()->minValue(0)->maxValue(50)->default(1)->required(),
            TextInput::make('max_guests')
                ->label(__('villa.fields.max_guests'))
                ->numeric()->integer()->minValue(1)->maxValue(100)->default(2)->required(),
            TextInput::make('min_nights')
                ->label(__('villa.fields.min_nights'))
                ->numeric()->integer()->minValue(1)->maxValue(60)->default(1)->required(),
            TextInput::make('base_price')
                ->label(__('villa.fields.base_price'))
                ->numeric()->prefix('Rp')->minValue(0)->required(),
            Toggle::make('is_active')
                ->label(__('villa.fields.is_active'))
                ->default(true)
                ->helperText(function () {
                    $tenant = Filament::getTenant();

                    return $tenant ? __('villa.quota.helper', [
                        'remaining' => RoomQuota::remaining($tenant),
                        'limit' => RoomQuota::limit($tenant),
                    ]) : null;
                })
                ->rule(fn (?Room $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                    $tenant = Filament::getTenant();

                    if ($value && $tenant && ! RoomQuota::canActivate($tenant, $record)) {
                        $fail(__('villa.quota.exceeded', ['limit' => RoomQuota::limit($tenant)]));
                    }
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('villa_name')
                    ->label(__('villa.fields.villa'))
                    ->state(fn (Room $record) => $record->villa?->localized('name')),
                TextColumn::make('name')
                    ->label(__('villa.fields.name'))
                    ->state(fn (Room $record) => $record->localized('name')),
                TextColumn::make('max_guests')
                    ->label(__('villa.fields.max_guests')),
                TextColumn::make('base_price')
                    ->label(__('villa.fields.base_price'))
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('villa.fields.is_active'))
                    ->boolean(),
            ])
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
            'index' => ManageRooms::route('/'),
        ];
    }
}
