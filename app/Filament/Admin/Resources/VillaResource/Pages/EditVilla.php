<?php

namespace App\Filament\Admin\Resources\VillaResource\Pages;

use App\Filament\Admin\Resources\VillaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVilla extends EditRecord
{
    protected static string $resource = VillaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
