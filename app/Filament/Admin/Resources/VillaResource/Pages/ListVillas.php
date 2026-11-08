<?php

namespace App\Filament\Admin\Resources\VillaResource\Pages;

use App\Filament\Admin\Resources\VillaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVillas extends ListRecords
{
    protected static string $resource = VillaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
