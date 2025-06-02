<?php

namespace App\Filament\Resources\ZlecenieResource\Pages;

use App\Filament\Resources\ZlecenieResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

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
}