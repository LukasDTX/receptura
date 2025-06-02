<?php

namespace App\Filament\Resources\RecepturaResource\Pages;

use App\Filament\Resources\RecepturaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateReceptura extends CreateRecord
{
    protected static string $resource = RecepturaResource::class;

    protected function afterCreate(): void
    {
        // Przelicz koszt całkowity i sumę procentową
        $this->record->refresh();    // Odświeżamy, aby mieć pewność, że mamy najnowsze dane
        $this->record->load('surowce'); // Ładujemy relację surowce
        $this->record->obliczKosztCalkowity();
        
        // Pokaż powiadomienie o sukcesie z informacją o sumie procentowej
        $sumaProcentowa = $this->record->getSumaProcentowa();
        
        $komunikat = 'Receptura została utworzona. Suma procentowa składników: ' . number_format($sumaProcentowa, 2) . '%';
        $typ = 'success';
        
        if ($sumaProcentowa < 99.5) {
            $komunikat .= ' (poniżej 100% - rozważ dodanie więcej składników)';
            $typ = 'warning';
        } elseif ($sumaProcentowa > 100.5) {
            $komunikat .= ' (powyżej 100% - rozważ zmniejszenie ilości składników)';
            $typ = 'danger';
        }
        
        Notification::make()
            ->title($komunikat)
            ->icon($typ === 'success' ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle')
            ->iconColor($typ)
            ->send();
    }
    
    // Dodane, aby przekierować do strony edycji po utworzeniu
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}