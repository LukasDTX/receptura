<?php

namespace App\Filament\Resources\RecepturaResource\Pages;

use App\Filament\Resources\RecepturaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateReceptura extends CreateRecord
{
    protected static string $resource = RecepturaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Upewnij się, że kod jest wygenerowany jeśli jest pusty
        if (empty($data['kod'])) {
            try {
                // Pobierz wszystkie kody zaczynające się od 'RCP-'
                $kody = \App\Models\Receptura::where('kod', 'LIKE', 'RCP-%')
                    ->pluck('kod')
                    ->toArray();
                
                $najwyzszyNumer = 0;
                
                foreach ($kody as $kod) {
                    // Wyciągnij numer z kodu (po 'RCP-')
                    if (preg_match('/^RCP-(\d+)$/', $kod, $matches)) {
                        $numer = (int) $matches[1];
                        if ($numer > $najwyzszyNumer) {
                            $najwyzszyNumer = $numer;
                        }
                    }
                }
                
                $nowyNumer = $najwyzszyNumer + 1;
                $data['kod'] = 'RCP-' . $nowyNumer;
                
            } catch (\Exception $e) {
                // Fallback - użyj count + 1
                $count = \App\Models\Receptura::count();
                $data['kod'] = 'RCP-' . ($count + 1);
            }
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            // Przelicz koszt całkowity i sumę procentową
            $this->record->refresh();
            $this->record->load('surowce');
            $this->record->obliczKosztCalkowity();
            
            // Pokaż powiadomienie o sukcesie z informacją o sumie procentowej
            $sumaProcentowa = $this->record->getSumaProcentowa();
            
            $komunikat = 'Receptura została utworzona z kodem: ' . $this->record->kod . '. Suma procentowa składników: ' . number_format($sumaProcentowa, 2) . '%';
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
                ->title('Receptura utworzona, ale wystąpił błąd podczas obliczania')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }
    
    // Dodane, aby przekierować do strony edycji po utworzeniu
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
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
            ->label('Zapisz recepturę')
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