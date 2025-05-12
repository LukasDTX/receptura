<?php

namespace App\Filament\Resources\ProduktResource\Pages;

use App\Filament\Resources\ProduktResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

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
}