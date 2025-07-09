<?php
// app/Filament/Resources/PartiaSurowcaResource/Pages/CreatePartiaSurowca.php

namespace App\Filament\Resources\PartiaSurowcaResource\Pages;

use App\Filament\Resources\PartiaSurowcaResource;
use App\Models\RuchSurowca;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePartiaSurowca extends CreateRecord
{
    protected static string $resource = PartiaSurowcaResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ustaw masa_pozostala równą masa_netto przy tworzeniu
        $data['masa_pozostala'] = $data['masa_netto'];
        $data['status'] = 'nowa';
        
        return $data;
    }
    
    protected function afterCreate(): void
    {
        // Automatycznie utwórz ruch przyjęcia
        RuchSurowca::create([
            'typ_ruchu' => 'przyjecie',
            'partia_surowca_id' => $this->record->id,
            'masa' => $this->record->masa_netto,
            'masa_przed' => 0,
            'masa_po' => $this->record->masa_netto,
            'skad' => 'dostawca',
            'dokad' => 'magazyn_glowny',
            'data_ruchu' => $this->record->data_przyjecia,
            'uwagi' => 'Przyjęcie nowej partii do magazynu',
            'user_id' => auth()->id(),
        ]);
        
        Notification::make()
            ->title('Partia utworzona')
            ->body('Partia została utworzona i dodana do magazynu wraz z ruchem przyjęcia.')
            ->success()
            ->send();
    }
}