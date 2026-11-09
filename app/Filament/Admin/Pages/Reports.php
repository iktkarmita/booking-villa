<?php

namespace App\Filament\Admin\Pages;

use App\Support\ReportService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Laporan owner: pendapatan, okupansi, dan booking per periode, plus ekspor CSV. */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.admin.pages.reports';

    public string $from = '';

    public string $to = '';

    public static function getNavigationLabel(): string
    {
        return __('report.nav');
    }

    public function getTitle(): string
    {
        return __('report.title');
    }

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function setPreset(string $preset): void
    {
        $today = CarbonImmutable::today();

        [$from, $to] = match ($preset) {
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_30' => [$today->subDays(29), $today],
            'year' => [$today->startOfYear(), $today],
            default => [$today->startOfMonth(), $today],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        [$from, $to] = $this->range();

        return ReportService::summary($from, $to);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(): array
    {
        try {
            $from = CarbonImmutable::parse($this->from)->startOfDay();
            $to = CarbonImmutable::parse($this->to)->startOfDay();
        } catch (\Throwable) {
            $from = CarbonImmutable::today()->startOfMonth();
            $to = CarbonImmutable::today();
        }

        // Batasi rentang maksimal 366 hari supaya query tetap ringan.
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > 365) {
            $from = $to->subDays(365);
        }

        return [$from, $to];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('report.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): StreamedResponse {
                    [$from, $to] = $this->range();

                    return response()->streamDownload(
                        fn () => print (ReportService::csv($from, $to)),
                        'booking-'.$from->toDateString().'_'.$to->toDateString().'.csv',
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    );
                }),
        ];
    }
}
