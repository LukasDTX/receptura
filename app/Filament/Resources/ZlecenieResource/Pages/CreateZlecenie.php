<?php

namespace App\Filament\Resources\ZlecenieResource\Pages;

use App\Filament\Resources\ZlecenieResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
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
        // Debug - sprawdź co jest w sesji
        \Illuminate\Support\Facades\Log::info('mutateFormDataBeforeCreate - start', [
            'session_keys' => array_keys(session()->all()),
            'temp_surowce' => session('temp_surowce_potrzebne'),
            'form_data_keys' => array_keys($data),
            'ilosc_zmieniona' => $data['ilosc_zmieniona'] ?? 'nie ustawione',
            'surowce_przeliczone' => $data['surowce_przeliczone'] ?? 'nie ustawione'
        ]);
        
        // WALIDACJA 1 - sprawdź czy ilość została zmieniona ale nie przeliczona
        if (isset($data['ilosc_zmieniona']) && 
            ($data['ilosc_zmieniona'] === true || $data['ilosc_zmieniona'] === 'true')) {
            
            Notification::make()
                ->title('Nie można zapisać')
                ->body('Przelicz surowce przed zapisaniem zlecenia. Kliknij ikonę kalkulatora przy polu "Ilość".')
                ->danger()
                ->persistent()
                ->send();
                
            throw new Halt('Przelicz surowce przed zapisaniem zlecenia.');
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
                
            throw new Halt('Przelicz surowce przed zapisaniem zlecenia.');
        }
        
        // Pobierz tymczasowe surowce z sesji
        $tempSurowce = session('temp_surowce_potrzebne');
        
        // Debug - szczegółowe sprawdzenie sesji
        \Illuminate\Support\Facades\Log::info('Sprawdzanie sesji surowców', [
            'temp_surowce_empty' => empty($tempSurowce),
            'temp_surowce_null' => is_null($tempSurowce),
            'temp_surowce_array' => is_array($tempSurowce),
            'temp_surowce_count' => is_array($tempSurowce) ? count($tempSurowce) : 'nie array',
            'temp_surowce_data' => $tempSurowce
        ]);
        
        if (empty($tempSurowce)) {
            // Spróbuj znaleźć alternatywne klucze sesji
            $allSessionKeys = array_keys(session()->all());
            $tempKeys = array_filter($allSessionKeys, function($key) {
                return str_contains($key, 'temp_surowce');
            });
            
            \Illuminate\Support\Facades\Log::warning('Brak danych o surowcach w sesji', [
                'all_session_keys' => $allSessionKeys,
                'temp_keys_found' => $tempKeys,
                'session_id' => session()->getId()
            ]);
            
            // Spróbuj użyć alternatywnego klucza jeśli istnieje
            if (!empty($tempKeys)) {
                $alternativeKey = reset($tempKeys);
                $tempSurowce = session($alternativeKey);
                \Illuminate\Support\Facades\Log::info('Użyto alternatywnego klucza sesji', [
                    'key' => $alternativeKey,
                    'data' => $tempSurowce
                ]);
            }
            
            if (empty($tempSurowce)) {
                Notification::make()
                    ->title('Błąd')
                    ->body('Brak danych o surowcach. Przelicz surowce ponownie.')
                    ->danger()
                    ->persistent()
                    ->send();
                    
                throw new Halt('Brak danych o surowcach.');
            }
        }
        
        $data['surowce_potrzebne'] = $tempSurowce;
        
        // Wyczyść wszystkie tymczasowe klucze sesji
        $allSessionKeys = array_keys(session()->all());
        foreach ($allSessionKeys as $key) {
            if (str_contains($key, 'temp_surowce')) {
                session()->forget($key);
            }
        }
        
        // Upewnij się, że numer zlecenia jest ustawiony
        if (empty($data['numer'])) {
            $rok = date('Y');
            $miesiac = date('m');
            $ostatnieId = \App\Models\Zlecenie::max('id') ?? 0;
            $noweId = $ostatnieId + 1;
            
            $data['numer'] = "ZP/{$rok}/{$miesiac}/{$noweId}";
        }
        
        \Illuminate\Support\Facades\Log::info('mutateFormDataBeforeCreate - end', [
            'surowce_count' => count($data['surowce_potrzebne']),
            'numer' => $data['numer']
        ]);
        
        return $data;
    }

    // NIE czyść sesji przy mount - może powodować problemy
    public function mount(): void
    {
        parent::mount();
        
        // Log mount
        \Illuminate\Support\Facades\Log::info('CreateZlecenie mount', [
            'session_id' => session()->getId(),
            'session_keys' => array_keys(session()->all())
        ]);
    }
        
    protected function afterCreate(): void
    {
        // Wyczyść wszystkie tymczasowe sesje po utworzeniu rekordu
        $allSessionKeys = array_keys(session()->all());
        foreach ($allSessionKeys as $key) {
            if (str_contains($key, 'temp_surowce')) {
                session()->forget($key);
            }
        }
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