<?php

namespace App\Filament\Resources\PartiaResource\Pages;

use App\Filament\Resources\PartiaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePartia extends CreateRecord
{
    protected static string $resource = PartiaResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Oblicz koszt produkcji na podstawie surowców
        $koszt = 0;
        if (!empty($data['surowce_uzyte'])) {
            foreach ($data['surowce_uzyte'] as $surowiec) {
                $koszt += $surowiec['koszt'] ?? 0;
            }
        }
        $data['koszt_produkcji'] = $koszt;
        
        return $data;
    }
    
    protected function afterCreate(): void
    {
        // Utwórz ruchy magazynowe dla partii
        $this->record->utworzRuchyMagazynowe();
        
        Notification::make()
            ->title('Partia utworzona')
            ->body('Partia została utworzona i dodana do magazynu wraz z ruchami magazynowymi.')
            ->success()
            ->send();
    }
}
