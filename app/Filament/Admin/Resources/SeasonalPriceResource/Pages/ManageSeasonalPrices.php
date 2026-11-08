<?php

namespace App\Filament\Admin\Resources\SeasonalPriceResource\Pages;

use App\Filament\Admin\Resources\SeasonalPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSeasonalPrices extends ManageRecords
{
    protected static string $resource = SeasonalPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
