<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TenantSettingResource\Pages\ManageTenantSettings;
use App\Models\TenantSetting;
use App\Support\Payment\GatewayFactory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Pengaturan pembayaran, rekening, dan kontak owner. Setiap tenant punya tepat satu baris. */
class TenantSettingResource extends Resource
{
    protected static ?string $model = TenantSetting::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 9;

    public static function getNavigationLabel(): string
    {
        return __('site.nav.settings');
    }

    public static function getModelLabel(): string
    {
        return __('site.nav.settings');
    }

    public static function getPluralModelLabel(): string
    {
        return __('site.nav.settings');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $t = 'site.owner.settings.';

        return $schema->columns(2)->components([
            Select::make('payment_mode')
                ->label(__($t.'payment_mode'))
                ->options([
                    'full' => __($t.'mode_full'),
                    'dp' => __($t.'mode_dp'),
                    'both' => __($t.'mode_both'),
                ])
                ->required()
                ->columnSpanFull(),
            TextInput::make('dp_percent')
                ->label(__($t.'dp_percent'))
                ->numeric()->integer()->minValue(1)->maxValue(99)->required(),
            TextInput::make('balance_due_days_before_checkin')
                ->label(__($t.'balance_days'))
                ->numeric()->integer()->minValue(0)->maxValue(90)->required(),
            TextInput::make('bank_name')
                ->label(__($t.'bank_name'))
                ->maxLength(100),
            TextInput::make('bank_account_number')
                ->label(__($t.'bank_account_number'))
                ->maxLength(50),
            TextInput::make('bank_account_name')
                ->label(__($t.'bank_account_name'))
                ->maxLength(100)
                ->columnSpanFull(),
            Select::make('gateway')
                ->label(__($t.'gateway'))
                ->options(fn () => collect(GatewayFactory::available())
                    ->mapWithKeys(fn (string $g) => [$g => __($t.'gateway_'.$g)])
                    ->all())
                ->default('manual')
                ->required()
                ->live()
                ->columnSpanFull(),
            TextInput::make('gateway_credentials.secret')
                ->label(__($t.'gateway_secret'))
                ->helperText(__($t.'gateway_secret_help'))
                ->password()
                ->revealable()
                ->dehydrated(fn (?string $state) => filled($state))
                ->visible(fn ($get) => $get('gateway') !== 'manual')
                ->columnSpanFull(),
            TextInput::make('wa_number')
                ->label(__($t.'wa_number'))
                ->helperText(__($t.'wa_help'))
                ->regex('/^[0-9]{8,16}$/')
                ->maxLength(16),
            TextInput::make('notify_email')
                ->label(__($t.'notify_email'))
                ->email()
                ->maxLength(150),
            TextInput::make('booking_hold_minutes')
                ->label(__($t.'hold'))
                ->numeric()->integer()->minValue(10)->maxValue(10080)->required()
                ->columnSpanFull(),
            Textarea::make('cancellation_policy.id')
                ->label(__($t.'policy_id'))
                ->rows(3),
            Textarea::make('cancellation_policy.en')
                ->label(__($t.'policy_en'))
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_mode')
                    ->label(__('site.owner.settings.payment_mode'))
                    ->formatStateUsing(fn (string $state) => __('site.owner.settings.mode_'.$state)),
                TextColumn::make('dp_percent')
                    ->label(__('site.owner.settings.dp_percent')),
                TextColumn::make('bank_name')
                    ->label(__('site.owner.settings.bank_name'))
                    ->placeholder('-'),
                TextColumn::make('wa_number')
                    ->label(__('site.owner.settings.wa_number'))
                    ->placeholder('-'),
            ])
            ->paginated(false)
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTenantSettings::route('/'),
        ];
    }
}
