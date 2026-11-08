<?php

namespace App\Filament\Admin\Resources;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingRuleViolated;
use App\Filament\Admin\Resources\BookingResource\Pages\ManageBookings;
use App\Models\Booking;
use App\Support\BookingService;
use App\Support\Money;
use App\Support\RefundPolicy;
use App\Support\RefundService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return __('site.nav.bookings');
    }

    public static function getModelLabel(): string
    {
        return __('site.nav.bookings');
    }

    public static function getPluralModelLabel(): string
    {
        return __('site.nav.bookings');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['guest', 'room.villa', 'payments']))
            ->columns([
                TextColumn::make('code')
                    ->label(__('site.owner.code'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('guest.name')
                    ->label(__('site.owner.guest'))
                    ->description(fn (Booking $record) => $record->guest?->phone)
                    ->searchable(),
                TextColumn::make('room_name')
                    ->label(__('site.owner.room'))
                    ->state(fn (Booking $record) => $record->room?->villa?->localized('name').' - '.$record->room?->localized('name')),
                TextColumn::make('check_in')
                    ->label(__('site.owner.dates'))
                    ->date('d/m/Y')
                    ->description(fn (Booking $record) => '→ '.$record->check_out->format('d/m/Y'))
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('site.owner.total'))
                    ->state(fn (Booking $record) => Money::idr($record->total))
                    ->description(fn (Booking $record) => __('site.owner.paid').': '.Money::idr($record->paid_amount)),
                TextColumn::make('status')
                    ->label(__('site.owner.status'))
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state) => $state->label())
                    ->color(fn (BookingStatus $state) => $state->color()),
                TextColumn::make('payment_status')
                    ->label(__('site.owner.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                    ->color(fn (PaymentStatus $state) => $state->color()),
                ImageColumn::make('proof')
                    ->label(__('site.owner.proof'))
                    ->disk('public')
                    ->state(fn (Booking $record) => $record->payments->whereNotNull('proof_path')->sortByDesc('id')->first()?->proof_path)
                    ->height(48),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('site.owner.status'))
                    ->options(collect(BookingStatus::cases())->mapWithKeys(fn (BookingStatus $s) => [$s->value => $s->label()])->all()),
            ])
            ->recordActions([
                Action::make('confirm_payment')
                    ->label(__('site.owner.confirm_payment'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record) => $record->pendingPayment() !== null && $record->status === BookingStatus::PendingPayment)
                    ->action(function (Booking $record): void {
                        self::run(fn () => BookingService::confirmPayment($record->pendingPayment()));
                    }),
                Action::make('record_balance')
                    ->label(__('site.owner.record_balance'))
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn], true) && $record->remaining() > 0)
                    ->schema([
                        TextInput::make('amount')
                            ->label(__('site.owner.amount'))
                            ->numeric()->prefix('Rp')->minValue(1)->required()
                            ->default(fn (Booking $record) => $record->remaining()),
                        TextInput::make('note')
                            ->label(__('site.owner.note'))
                            ->maxLength(150),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        self::run(fn () => BookingService::recordBalance($record, (int) $data['amount'], $data['note'] ?? null));
                    }),
                Action::make('cancel_booking')
                    ->label(__('site.owner.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record) => $record->status->holdsDates())
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('site.owner.cancel_reason'))
                            ->rows(2)
                            ->maxLength(250),
                        TextInput::make('refund_amount')
                            ->label(__('site.owner.refund_on_cancel'))
                            ->numeric()->prefix('Rp')->minValue(0)
                            ->default(fn (Booking $record) => RefundPolicy::suggestedAmount($record))
                            ->visible(fn (Booking $record) => $record->paid_amount > 0),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        self::run(fn () => RefundService::ownerCancel($record, $data['reason'] ?? null, (int) ($data['refund_amount'] ?? 0)));
                    }),
            ]);
    }

    private static function run(callable $callback): void
    {
        try {
            $callback();
        } catch (BookingRuleViolated $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('site.owner.done'))->success()->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBookings::route('/'),
        ];
    }
}
