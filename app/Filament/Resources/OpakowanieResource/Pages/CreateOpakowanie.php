<?php

namespace App\Filament\Resources\OpakowanieResource\Pages;

use App\Filament\Resources\OpakowanieResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateOpakowanie extends CreateRecord
{
    protected static string $resource = OpakowanieResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Opakowanie zapisane')
            ->body('Nowe opakowanie zostało pomyślnie utworzone.');
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
            ->label('Zapisz produkt')
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

