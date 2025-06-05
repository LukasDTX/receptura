<?php

namespace App\Filament\Resources\ZlecenieResource\Pages;

use App\Filament\Resources\ZlecenieResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Actions\Action;

class EditZlecenie extends EditRecord
{
    protected static string $resource = ZlecenieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('przeliczSurowce')
                ->label('Przelicz surowce')
                ->icon('heroicon-o-calculator')
                ->hidden(true)
                ->action(function () {
                    try {
                        // Zachowaj aktualny stan formularza
                        $this->form->getState();
                        
                        // Przelicz surowce - ale nie zapisuj całego rekordu
                        $wynik = $this->record->obliczPotrzebneSurowcePreview();
                        
                        // Zapisz tylko pole surowce_potrzebne
                        $this->record->surowce_potrzebne = $wynik;
                        $this->record->timestamps = false; // Wyłącz aktualizację timestamps
                        $this->record->save(['timestamps' => false]);
                        $this->record->timestamps = true; // Włącz z powrotem timestamps
                        
                        Notification::make()
                            ->title('Surowce przeliczone')
                            ->body('Lista potrzebnych surowców została zaktualizowana.')
                            ->success()
                            ->send();
                        
                        // Odśwież formularz, aby pokazać zaktualizowane dane
                        $this->fillForm();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Błąd podczas przeliczania surowców')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                            
                        // Loguj błąd
                        \Illuminate\Support\Facades\Log::error('Błąd podczas przeliczania surowców: ' . $e->getMessage(), [
                            'zlecenie_id' => $this->record->id,
                            'exception' => $e,
                        ]);
                    }
            }),
            Actions\Action::make('drukuj')
                ->label('Drukuj zlecenie')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('zlecenie.drukuj', $this->record))
                ->openUrlInNewTab(),
        ];
    }
    
    protected function afterSave(): void
    {
        // Jeśli zmieniono produkt lub ilość, automatycznie przeliczamy potrzebne surowce
        if ($this->record->isDirty(['produkt_id', 'ilosc'])) {
            try {
                $this->record->refresh(); // Odśwież rekord, aby mieć aktualne dane
                $this->record->obliczPotrzebneSurowce();
                
                Notification::make()
                    ->title('Surowce przeliczone')
                    ->body('Lista potrzebnych surowców została zaktualizowana po zmianie produktu lub ilości.')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Błąd podczas przeliczania surowców')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
                    
                // Loguj błąd
                \Illuminate\Support\Facades\Log::error('Błąd podczas przeliczania surowców: ' . $e->getMessage(), [
                    'zlecenie_id' => $this->record->id,
                    'exception' => $e,
                ]);
            }
        }
    }
        // Przekierowanie po zapisaniu zmian
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    // Dostosowanie akcji formularza z własnymi nazwami
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
            //$this->getDeleteAction(), // Opcjonalnie dodaj przycisk usuwania
        ];
    }
    
    // Nadanie własnej nazwy przyciskowi "Save"
    // protected function getSaveFormAction(): \Filament\Actions\Action
    // {
    //     return parent::getSaveFormAction()
    //         ->label('Zapisz zmiany')
    //         ->icon('heroicon-o-check')
    //         ->color('success')
    //         ->disabled(function (array $data): bool {
    //             // Zablokuj przycisk jeśli ilość została zmieniona ale surowce nie zostały przeliczone
    //             return isset($data['ilosc_zmieniona']) && 
    //                    ($data['ilosc_zmieniona'] === true || $data['ilosc_zmieniona'] === 'true');
    //         })
    //         ->tooltip(function (array $data): ?string {
    //             if (isset($data['ilosc_zmieniona']) && 
    //                 ($data['ilosc_zmieniona'] === true || $data['ilosc_zmieniona'] === 'true')) {
    //                 return 'Przelicz surowce przed zapisaniem zmian';
    //             }
    //             return null;
    //         });
    // }

    // Nadanie własnej nazwy przyciskowi "Save"
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Zapisz zmiany')
            ->icon('heroicon-o-check')
            ->color('success');
    }
    
    // GŁÓWNA WALIDACJA - to zadziała na pewno
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Sprawdź czy ilość została zmieniona ale nie przeliczona
        if (isset($data['ilosc_zmieniona']) && 
            ($data['ilosc_zmieniona'] === true || $data['ilosc_zmieniona'] === 'true')) {
            
            // Zatrzymaj zapis i pokaż błąd
            \Filament\Notifications\Notification::make()
                ->title('Nie można zapisać')
                ->body('Przelicz surowce przed zapisaniem zmian.')
                ->danger()
                ->persistent()
                ->send();
                
            // Rzuć wyjątek żeby zatrzymać zapis
            throw new Halt('Przelicz surowce przed zapisaniem zmian.');
        }
        
        return $data;
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