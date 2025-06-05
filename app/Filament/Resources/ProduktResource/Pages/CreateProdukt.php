<?php

namespace App\Filament\Resources\ProduktResource\Pages;

use App\Filament\Resources\ProduktResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateProdukt extends CreateRecord
{
    protected static string $resource = ProduktResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $receptura = \App\Models\Receptura::find($data['receptura_id']);
        $opakowanie = \App\Models\Opakowanie::find($data['opakowanie_id']);
        
        if ($receptura && $opakowanie) {
            // Upewnij się, że koszt receptury jest aktualny
            $receptura->obliczKosztCalkowity();
            
            // Oblicz całkowity koszt produktu
            $data['koszt_calkowity'] = $receptura->koszt_calkowity + $opakowanie->cena;
        } else {
            // Jeśli z jakiegoś powodu receptura lub opakowanie nie zostały znalezione,
            // ustaw koszt całkowity na 0, aby uniknąć naruszenia ograniczenia NOT NULL
            $data['koszt_calkowity'] = 0;
        }
        
        return $data;
    }
    // Przekierowanie po utworzeniu rekordu
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Produkt zapisany')
            ->body('Nowy produkt został pomyślnie utworzony.');
    }
    // Opcjonalnie: dostosowanie akcji formularza
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            //$this->getCreateAnotherFormAction(),
            
            // Niestandardowa akcja
            // \Filament\Actions\Action::make('save_and_view')
            //     ->label('Zapisz i zobacz')
            //     ->icon('heroicon-o-eye')
            //     ->color('info')
            //     ->action(function () {
            //         $this->create();
            //         return redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
            //     }),
            $this->getCancelFormAction(),
        ];
    }
    
    // Opcjonalnie: zmiana etykiety przycisku "Create & create another"
    // protected function getCreateAnotherFormAction(): \Filament\Actions\Action
    // {
    //     return parent::getCreateAnotherFormAction()
    //         ->label('Zapisz i utwórz kolejny');
    // }
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