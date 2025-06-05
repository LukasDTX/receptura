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
            Actions\Action::make('refresh')
                ->label('Odśwież obliczenia')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    try {
                        $this->record->refresh();
                        $this->record->load('surowce');
                        $this->record->obliczKosztCalkowity();
                        $this->fillForm();
                        
                        Notification::make()
                            ->title('Obliczenia odświeżone')
                            ->body('Koszt i suma procentowa została przeliczona.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Błąd podczas odświeżania')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        try {
            // Przelicz koszt całkowity i sumę procentową
            $this->record->refresh();
            $this->record->load('surowce');
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
                ->color($typ)
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Błąd podczas przeliczania')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    // Dodane, aby po każdej modyfikacji surowców odświeżać dane
    protected function afterFormFilled(): void
    {
        try {
            // Przelicz koszt całkowity i sumę procentową
            $this->record->refresh();
            $this->record->load('surowce');
            $this->record->obliczKosztCalkowity();
        } catch (\Exception $e) {
            // Ciche niepowodzenie, aby nie blokować ładowania formularza
            \Illuminate\Support\Facades\Log::warning('Nie udało się przeliczyć kosztu przy ładowaniu formularza: ' . $e->getMessage());
        }
    }
    
    // Zapobiega przekierowaniu po zapisie, aby pozostać na stronie edycji
    protected function getRedirectUrl(): ?string
    {
        return null;
    }
    
    // Dostosowanie akcji formularza z własnymi nazwami
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }
    
    // Nadanie własnej nazwy przyciskowi "Save"
    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()
            ->label('Zapisz zmiany')
            ->icon('heroicon-o-check')
            ->color('success');
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