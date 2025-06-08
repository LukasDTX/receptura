<?php

namespace App\Filament\Resources\PartiaResource\Pages;

use App\Filament\Resources\PartiaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPartia extends ViewRecord
{
    protected static string $resource = PartiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}