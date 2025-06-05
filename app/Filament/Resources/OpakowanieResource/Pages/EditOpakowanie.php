<?php

namespace App\Filament\Resources\OpakowanieResource\Pages;

use App\Filament\Resources\OpakowanieResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class EditOpakowanie extends EditRecord
{
    protected static string $resource = OpakowanieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Sprawdź czy ktoś próbuje zmienić jednostkę
        if ($this->record->jednostka !== $data['jednostka']) {
            
            // Sprawdź czy opakowanie jest używane w produktach
            $produktyCount = $this->record->produkty()->count();
            
            if ($produktyCount > 0) {
                Notification::make()
                    ->title('Nie można zmienić jednostki')
                    ->body("Opakowanie jest używane w {$produktyCount} produktach. Zmiana jednostki mogłaby spowodować błędy w obliczeniach.")
                    ->danger()
                    ->persistent()
                    ->send();
                    
                throw new Halt('Nie można zmienić jednostki opakowania używanego w produktach.');
            } else {
                Notification::make()
                    ->title('Ostrzeżenie')
                    ->body('Zmiana jednostki opakowania może wpłynąć na kompatybilność z recepturami.')
                    ->warning()
                    ->send();
            }
        }
        
        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Zapisane')
            ->body('Zmiany w opakowaniu zostały pomyślnie zapisane.');
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