<?php

namespace App\Filament\Resources\OpakowanieResource\Pages;

use App\Filament\Resources\OpakowanieResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditOpakowanie extends EditRecord
{
    protected static string $resource = OpakowanieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
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
            ->body('Zmiany w produkcie zostały pomyślnie zapisane.');
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
