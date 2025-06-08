<?php
// ListParties.php
namespace App\Filament\Resources\PartiaResource\Pages;

use App\Filament\Resources\PartiaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartias extends ListRecords
{
    protected static string $resource = PartiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj partię')
                ->icon('heroicon-o-plus'),
        ];
    }
}
