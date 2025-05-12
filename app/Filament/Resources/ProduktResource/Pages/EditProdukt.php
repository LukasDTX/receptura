<?php

namespace App\Filament\Resources\ProduktResource\Pages;

use App\Filament\Resources\ProduktResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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
}