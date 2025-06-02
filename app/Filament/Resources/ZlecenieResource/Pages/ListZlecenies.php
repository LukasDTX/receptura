<?php

namespace App\Filament\Resources\ZlecenieResource\Pages;

use App\Filament\Resources\ZlecenieResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListZlecenies extends ListRecords
{
    protected static string $resource = ZlecenieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}