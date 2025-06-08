<?php

namespace App\Filament\Resources\StanMagazynuResource\Pages;

use App\Filament\Resources\StanMagazynuResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStanMagazynu extends \Filament\Resources\Pages\CreateRecord
{
    protected static string $resource = StanMagazynuResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function afterCreate(): void
    {
        // Utwórz ruch magazynowy dla początkowego stanu
        \App\Models\RuchMagazynowy::create([
            'typ_ruchu' => \App\Enums\TypRuchuMagazynowego::PRZYJECIE,
            'typ_towaru' => $this->record->typ_towaru,
            'towar_id' => $this->record->towar_id,
            'numer_partii' => $this->record->numer_partii,
            'ilosc' => $this->record->ilosc_dostepna,
            'jednostka' => $this->record->jednostka,
            'cena_jednostkowa' => $this->record->ilosc_dostepna > 0 ? $this->record->wartosc / $this->record->ilosc_dostepna : 0,
            'wartosc' => $this->record->wartosc,
            'data_ruchu' => now(),
            'zrodlo_docelowe' => 'Stan początkowy',
            'uwagi' => 'Wprowadzenie stanu początkowego do magazynu',
            'user_id' => auth()->id(),
        ]);
    }
}