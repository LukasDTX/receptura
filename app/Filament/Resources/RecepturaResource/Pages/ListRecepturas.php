<?php

namespace App\Filament\Resources\RecepturaResource\Pages;

use App\Filament\Resources\RecepturaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecepturas extends ListRecords
{
    protected static string $resource = RecepturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
        Actions\CreateAction::make()
            ->label('Dodaj recepture')
            ->icon('heroicon-o-plus')
            ->color('success') // zielony kolor
            ->size('lg'), // większy rozmiar
        ];
    }
}