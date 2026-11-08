<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\VillaResource\Pages\CreateVilla;
use App\Filament\Admin\Resources\VillaResource\Pages\EditVilla;
use App\Filament\Admin\Resources\VillaResource\Pages\ListVillas;
use App\Models\Amenity;
use App\Models\Villa;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VillaResource extends Resource
{
    protected static ?string $model = Villa::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('villa.nav.villas');
    }

    public static function getModelLabel(): string
    {
        return __('villa.models.villa');
    }

    public static function getPluralModelLabel(): string
    {
        return __('villa.nav.villas');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('villa.sections.info'))
                ->columns(2)
                ->schema([
                    TextInput::make('name.id')
                        ->label(__('villa.fields.name_id'))
                        ->required()
                        ->maxLength(150),
                    TextInput::make('name.en')
                        ->label(__('villa.fields.name_en'))
                        ->maxLength(150),
                    Textarea::make('description.id')
                        ->label(__('villa.fields.description_id'))
                        ->rows(4),
                    Textarea::make('description.en')
                        ->label(__('villa.fields.description_en'))
                        ->rows(4),
                    TextInput::make('address')
                        ->label(__('villa.fields.address'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('check_in_time')
                        ->label(__('villa.fields.check_in_time'))
                        ->type('time')
                        ->default('14:00')
                        ->required(),
                    TextInput::make('check_out_time')
                        ->label(__('villa.fields.check_out_time'))
                        ->type('time')
                        ->default('12:00')
                        ->required(),
                    Toggle::make('is_active')
                        ->label(__('villa.fields.is_active'))
                        ->default(true),
                ]),

            Section::make(__('villa.sections.amenities'))
                ->schema([
                    CheckboxList::make('amenities')
                        ->hiddenLabel()
                        ->relationship('amenities', 'code')
                        ->getOptionLabelFromRecordUsing(fn (Amenity $record) => $record->localized('name'))
                        ->columns(3),
                ]),

            Section::make(__('villa.sections.photos'))
                ->schema([
                    Repeater::make('images')
                        ->hiddenLabel()
                        ->relationship('images')
                        ->orderColumn('sort_order')
                        ->schema([
                            FileUpload::make('path')
                                ->label(__('villa.fields.image'))
                                ->image()
                                ->disk('public')
                                ->directory('villas')
                                ->maxSize(4096)
                                ->required(),
                        ])
                        ->addActionLabel(__('villa.fields.images'))
                        ->grid(3)
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('villa.fields.name'))
                    ->state(fn (Villa $record) => $record->localized('name')),
                TextColumn::make('address')
                    ->label(__('villa.fields.address'))
                    ->limit(40),
                TextColumn::make('rooms_count')
                    ->label(__('villa.fields.rooms_count'))
                    ->counts('rooms'),
                IconColumn::make('is_active')
                    ->label(__('villa.fields.is_active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVillas::route('/'),
            'create' => CreateVilla::route('/create'),
            'edit' => EditVilla::route('/{record}/edit'),
        ];
    }
}
