<?php
// ListRuchMagazynowies.php
namespace App\Filament\Resources\RuchMagazynowyResource\Pages;

use App\Filament\Resources\RuchMagazynowyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRuchMagazynowies extends ListRecords
{
    protected static string $resource = RuchMagazynowyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj ruch magazynowy')
                ->icon('heroicon-o-plus'),
        ];
    }
}
