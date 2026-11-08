<?php

namespace App\Filament\Admin\Resources;

use App\Exceptions\BookingRuleViolated;
use App\Filament\Admin\Resources\RefundResource\Pages\ManageRefunds;
use App\Models\Refund;
use App\Support\Money;
use App\Support\RefundService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Pengajuan pembatalan/refund: owner menyetujui (menetapkan nominal), menolak, lalu menandai sudah ditransfer. */
class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('site.nav.refunds');
    }

    public static function getModelLabel(): string
    {
        return __('site.nav.refunds');
    }

    public static function getPluralModelLabel(): string
    {
        return __('site.nav.refunds');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $statuses = collect(['requested', 'approved', 'rejected', 'refunded'])
            ->mapWithKeys(fn (string $s) => [$s => __('site.owner.refund_status_'.$s)])
            ->all();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['booking.guest']))
            ->columns([
                TextColumn::make('booking.code')->label(__('site.owner.code'))->searchable(),
                TextColumn::make('booking.guest.name')->label(__('site.owner.guest')),
                TextColumn::make('status')
                    ->label(__('site.owner.refund_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $statuses[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'requested' => 'warning',
                        'approved' => 'info',
                        'refunded' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('suggested_amount')
                    ->label(__('site.owner.refund_suggested'))
                    ->formatStateUsing(fn ($state) => Money::idr((int) $state)),
                TextColumn::make('amount_approved')
                    ->label(__('site.owner.refund_amount'))
                    ->formatStateUsing(fn ($state) => $state === null ? '-' : Money::idr((int) $state)),
                TextColumn::make('dest_bank')
                    ->label(__('site.owner.refund_dest'))
                    ->state(fn (Refund $record) => filled($record->dest_bank)
                        ? $record->dest_bank.' · '.$record->dest_account_number
                        : null)
                    ->description(fn (Refund $record) => $record->dest_account_name)
                    ->placeholder('-'),
                TextColumn::make('reason')->label(__('site.owner.refund_reason'))->limit(40)->tooltip(fn (Refund $record) => $record->reason),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('site.owner.refund_status'))->options($statuses),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('site.owner.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Refund $record) => $record->status === 'requested')
                    ->schema([
                        TextInput::make('amount')
                            ->label(__('site.owner.refund_amount'))
                            ->numeric()->prefix('Rp')->minValue(0)->required()
                            ->default(fn (Refund $record) => $record->suggested_amount),
                        Textarea::make('note')->label(__('site.owner.note'))->rows(2)->maxLength(250),
                    ])
                    ->action(function (Refund $record, array $data): void {
                        self::run(fn () => RefundService::approve($record, (int) $data['amount'], $data['note'] ?? null));
                    }),
                Action::make('reject')
                    ->label(__('site.owner.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Refund $record) => $record->status === 'requested')
                    ->schema([
                        Textarea::make('note')->label(__('site.owner.note'))->rows(2)->maxLength(250),
                    ])
                    ->action(function (Refund $record, array $data): void {
                        self::run(fn () => RefundService::reject($record, $data['note'] ?? null));
                    }),
                Action::make('mark_refunded')
                    ->label(__('site.owner.mark_refunded'))
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Refund $record) => $record->status === 'approved')
                    ->schema([
                        FileUpload::make('proof')
                            ->label(__('site.owner.proof_transfer'))
                            ->image()->disk('public')->directory('refund-proofs')->maxSize(4096),
                        Textarea::make('note')->label(__('site.owner.note'))->rows(2)->maxLength(250),
                    ])
                    ->action(function (Refund $record, array $data): void {
                        self::run(fn () => RefundService::markRefunded($record, $data['proof'] ?? null, $data['note'] ?? null));
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
            'index' => ManageRefunds::route('/'),
        ];
    }
}
