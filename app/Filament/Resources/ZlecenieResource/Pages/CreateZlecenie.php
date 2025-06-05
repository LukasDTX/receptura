<?php

namespace App\Filament\Resources\ZlecenieResource\Pages;

use App\Filament\Resources\ZlecenieResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateZlecenie extends CreateRecord
{
    protected static string $resource = ZlecenieResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
    
protected function mutateFormDataBeforeCreate(array $data): array
{
    // Na początku zawsze wyczyść starą sesję
    session()->forget('temp_surowce_potrzebne');
    
    // WALIDACJA 1 - sprawdź czy ilość została zmieniona ale nie przeliczona
    if (isset($data['ilosc_zmieniona']) && 
        ($data['ilosc_zmieniona'] === true || $data['ilosc_zmieniona'] === 'true')) {
        
        Notification::make()
            ->title('Nie można zapisać')
            ->body('Przelicz surowce przed zapisaniem zlecenia. Kliknij ikonę kalkulatora przy polu "Ilość".')
            ->danger()
            ->persistent()
            ->send();
            
        throw new \Filament\Actions\Exceptions\Halt('Przelicz surowce przed zapisaniem zlecenia.');
    }
    
    // WALIDACJA 2 - sprawdź czy surowce zostały przeliczone
    if (!isset($data['surowce_przeliczone']) || 
        ($data['surowce_przeliczone'] !== true && $data['surowce_przeliczone'] !== 'true')) {
        
        Notification::make()
            ->title('Nie można zapisać')
            ->body('Musisz najpierw przeliczyć surowce. Wybierz produkt, ustaw ilość i kliknij przycisk kalkulatora.')
            ->danger()
            ->persistent()
            ->send();
            
        throw new \Filament\Actions\Exceptions\Halt('Przelicz surowce przed zapisaniem zlecenia.');
    }
    
    // Pobierz tymczasowe surowce z sesji
    $tempSurowce = session('temp_surowce_potrzebne');
    if (empty($tempSurowce)) {
        Notification::make()
            ->title('Błąd')
            ->body('Brak danych o surowcach. Przelicz surowce ponownie.')
            ->danger()
            ->persistent()
            ->send();
            
        throw new \Filament\Actions\Exceptions\Halt('Brak danych o surowcach.');
    }
    
    $data['surowce_potrzebne'] = $tempSurowce;
    session()->forget('temp_surowce_potrzebne');
    
    // Upewnij się, że numer zlecenia jest ustawiony
    if (empty($data['numer'])) {
        $rok = date('Y');
        $miesiac = date('m');
        $ostatnieId = \App\Models\Zlecenie::max('id') ?? 0;
        $noweId = $ostatnieId + 1;
        
        $data['numer'] = "ZP/{$rok}/{$miesiac}/{$noweId}";
    }
    
    return $data;
}

// Dodaj też metodę mount() żeby wyczyścić sesję przy wejściu na stronę:
public function mount(): void
{
    parent::mount();
    
    // Wyczyść starą sesję przy wchodzeniu na create
    session()->forget('temp_surowce_potrzebne');
}
    
    protected function afterCreate(): void
    {
        // Wyczyść sesję po utworzeniu rekordu
        session()->forget('temp_surowce_potrzebne');
    }
    
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Zlecenie zapisane')
            ->body('Nowe zlecenie zostało pomyślnie utworzone.');
    }
    
    // Opcjonalnie: dostosowanie akcji formularza
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }
    
    // Nadanie własnej nazwy przyciskowi "Create"
    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Zapisz zlecenie')
            ->icon('heroicon-o-check')
            ->color('success')
            ->extraAttributes([
                'id' => 'create-zlecenie-submit-btn'
            ]);
    }
    
    // Nadanie własnej nazwy przyciskowi "Cancel"
    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Anuluj')
            ->icon('heroicon-o-x-mark')
            ->color('danger');
    }
}