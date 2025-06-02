<?php

namespace App\Filament\Resources\RecepturaResource\Pages;

use App\Filament\Resources\RecepturaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditReceptura extends EditRecord
{
    protected static string $resource = RecepturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Przelicz koszt całkowity i sumę procentową
        $this->record->refresh();    // Odświeżamy, aby mieć pewność, że mamy najnowsze dane
        $this->record->load('surowce'); // Ładujemy relację surowce
        $this->record->obliczKosztCalkowity();
        
        // Odśwież formularz, aby pokazać nowe wartości
        $this->fillForm();
        
        // Pokaż powiadomienie o sukcesie z informacją o sumie procentowej
        $sumaProcentowa = $this->record->getSumaProcentowa();
        
        $komunikat = 'Receptura została zaktualizowana. Suma procentowa składników: ' . number_format($sumaProcentowa, 2) . '%';
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
    
    // Dodane, aby po każdej modyfikacji surowców odświeżać dane
    protected function afterFormFilled(): void
    {
        // Wywołane, gdy formularz jest wypełniany danymi z rekordu
        // Przelicz koszt całkowity i sumę procentową
        $this->record->refresh();
        $this->record->load('surowce');
        $this->record->obliczKosztCalkowity();
    }
    
    // Zapobiega przekierowaniu po zapisie, aby pozostać na stronie edycji
    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}