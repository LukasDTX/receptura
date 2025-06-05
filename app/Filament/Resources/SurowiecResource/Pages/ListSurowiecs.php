<?php

namespace App\Filament\Resources\SurowiecResource\Pages;

use App\Filament\Resources\SurowiecResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSurowiecs extends ListRecords
{
    protected static string $resource = SurowiecResource::class;

    protected function getHeaderActions(): array
    {
        return [
        Actions\CreateAction::make()
            ->label('Dodaj surowiec')
            ->icon('heroicon-o-plus')
            ->color('success') // zielony kolor
            ->size('lg'), // większy rozmiar
        ];
    }
}
