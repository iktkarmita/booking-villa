<?php

namespace App\Filament\Admin\Pages;

use App\Exceptions\DatesUnavailable;
use App\Models\CalendarDay;
use App\Models\Room;
use App\Support\Availability;
use App\Support\PriceCalculator;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/** Kalender ketersediaan per kamar: lihat terisi/diblokir, blokir & buka blokir tanggal. */
class RoomCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'calendar';

    protected string $view = 'filament.admin.pages.room-calendar';

    public ?int $roomId = null;

    public string $month = '';

    public static function getNavigationLabel(): string
    {
        return __('calendar.nav.calendar');
    }

    public function getTitle(): string
    {
        return __('calendar.page.title');
    }

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->roomId = Room::query()->where('is_active', true)->orderBy('id')->value('id');
    }

    public function previousMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonth()->format('Y-m');
    }

    /** @return Collection<int,string> id kamar => "Villa - Kamar" */
    public function roomOptions(): Collection
    {
        return Room::query()->with('villa')->where('is_active', true)->orderBy('id')->get()
            ->mapWithKeys(fn (Room $room) => [$room->getKey() => $room->villa?->localized('name').' - '.$room->localized('name')]);
    }

    public function monthLabel(): string
    {
        return CarbonImmutable::parse($this->month.'-01')->translatedFormat('F Y');
    }

    /**
     * Sel kalender: null = kosong (padding), selain itu info per hari.
     *
     * @return array<int, array{day:int,date:string,status:string,note:?string,price:int}|null>
     */
    public function cells(): array
    {
        $room = $this->roomId ? Room::query()->find($this->roomId) : null;

        if ($room === null) {
            return [];
        }

        $first = CarbonImmutable::parse($this->month.'-01');
        $last = $first->endOfMonth();

        $days = CalendarDay::query()
            ->where('room_id', $room->getKey())
            ->whereBetween('date', [$first->toDateString(), $last->toDateString()])
            ->get()
            ->keyBy(fn (CalendarDay $day) => $day->date->toDateString());

        $quote = PriceCalculator::quote($room, $first, $last->addDay());

        $cells = array_fill(0, $first->dayOfWeekIso - 1, null);

        for ($date = $first; $date->lessThanOrEqualTo($last); $date = $date->addDay()) {
            $key = $date->toDateString();
            $entry = $days->get($key);

            $cells[] = [
                'day' => $date->day,
                'date' => $key,
                'status' => $entry?->type ?? 'free',
                'note' => $entry?->note,
                'price' => $quote['nightly'][$key] ?? (int) $room->base_price,
            ];
        }

        return $cells;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('block')
                ->label(__('calendar.actions.block'))
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->schema($this->rangeSchema(withNote: true))
                ->action(function (array $data): void {
                    $room = Room::query()->findOrFail($data['room_id']);

                    try {
                        $count = Availability::block($room, $data['from'], $data['until'], $data['note'] ?? null);
                    } catch (DatesUnavailable) {
                        Notification::make()->title(__('calendar.actions.conflict'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('calendar.actions.blocked_done', ['count' => $count]))->success()->send();
                }),
            Action::make('unblock')
                ->label(__('calendar.actions.unblock'))
                ->icon('heroicon-o-lock-open')
                ->color('gray')
                ->schema($this->rangeSchema(withNote: false))
                ->action(function (array $data): void {
                    $room = Room::query()->findOrFail($data['room_id']);
                    $count = Availability::unblock($room, $data['from'], $data['until']);

                    Notification::make()->title(__('calendar.actions.unblocked_done', ['count' => $count]))->success()->send();
                }),
        ];
    }

    /** @return array<int, mixed> */
    private function rangeSchema(bool $withNote): array
    {
        $fields = [
            Select::make('room_id')
                ->label(__('calendar.fields.room'))
                ->options(fn () => $this->roomOptions())
                ->default(fn () => $this->roomId)
                ->required(),
            DatePicker::make('from')
                ->label(__('calendar.fields.start_date'))
                ->native(false)
                ->required(),
            DatePicker::make('until')
                ->label(__('calendar.fields.end_date'))
                ->native(false)
                ->required()
                ->afterOrEqual('from'),
        ];

        if ($withNote) {
            $fields[] = TextInput::make('note')
                ->label(__('calendar.fields.note'))
                ->maxLength(150);
        }

        return $fields;
    }
}
