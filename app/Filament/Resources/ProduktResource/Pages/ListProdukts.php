<?php

namespace App\Filament\Resources\ProduktResource\Pages;

use App\Filament\Resources\ProduktResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProdukts extends ListRecords
{
    protected static string $resource = ProduktResource::class;

    protected function getHeaderActions(): array
    {
        return [
        Actions\CreateAction::make()
            ->label('Dodaj produkt')
            ->icon('heroicon-o-plus')
            ->color('success') // zielony kolor
            ->size('lg'), // większy rozmiar
        ];
    }
}