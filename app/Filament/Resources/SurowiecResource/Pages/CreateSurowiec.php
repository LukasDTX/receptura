<?php

namespace App\Filament\Resources\SurowiecResource\Pages;

use App\Filament\Resources\SurowiecResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateSurowiec extends CreateRecord
{
    protected static string $resource = SurowiecResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Surowiece zapisany')
            ->body('Nowy surowiec został pomyślnie utworzone.');
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
            ->label('Zapisz surowiec')
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
