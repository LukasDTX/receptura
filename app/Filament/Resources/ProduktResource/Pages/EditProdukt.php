<?php

namespace App\Filament\Resources\ProduktResource\Pages;

use App\Filament\Resources\ProduktResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditProdukt extends EditRecord
{
    protected static string $resource = ProduktResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $receptura = \App\Models\Receptura::find($data['receptura_id']);
        $opakowanie = \App\Models\Opakowanie::find($data['opakowanie_id']);
        
        if ($receptura && $opakowanie) {
            // Upewnij się, że koszt receptury jest aktualny
            $receptura->obliczKosztCalkowity();
            
            // Oblicz całkowity koszt produktu
            $data['koszt_calkowity'] = $receptura->koszt_calkowity + $opakowanie->cena;
        }
        
        return $data;
    }
    // Przekierowanie po zapisaniu zmian
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
    
    // Opcjonalnie: przycisk usuwania z własną nazwą
    // protected function getDeleteAction(): \Filament\Actions\DeleteAction
    // {
    //     return \Filament\Actions\DeleteAction::make()
    //         ->label('Usuń produkt')
    //         ->icon('heroicon-o-trash')
    //         ->requiresConfirmation()
    //         ->modalHeading('Usuń produkt')
    //         ->modalDescription('Czy na pewno chcesz usunąć ten produkt? Ta akcja jest nieodwracalna.')
    //         ->modalSubmitActionLabel('Usuń')
    //         ->modalCancelActionLabel('Anuluj');
    // }
}