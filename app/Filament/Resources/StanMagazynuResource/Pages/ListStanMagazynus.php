<?php
// ListStanMagazynus.php
namespace App\Filament\Resources\StanMagazynuResource\Pages;

use App\Filament\Resources\StanMagazynuResource;
use App\Filament\Resources\RuchMagazynowyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStanMagazynus extends ListRecords
{
    protected static string $resource = StanMagazynuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj pozycję')
                ->icon('heroicon-o-plus'),
            Actions\Action::make('nowy_ruch')
                ->label('Nowy ruch magazynowy')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('primary')
                ->url(RuchMagazynowyResource::getUrl('create')),
            Actions\Action::make('ruchy_magazynowe')
                ->label('Zobacz wszystkie ruchy')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(RuchMagazynowyResource::getUrl('index')),
        ];
    }
}
