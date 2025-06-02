<?php

namespace App\Filament\Resources\ZlecenieResource\Pages;

use App\Filament\Resources\ZlecenieResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateZlecenie extends CreateRecord
{
    protected static string $resource = ZlecenieResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Upewnij się, że numer zlecenia jest ustawiony
        if (empty($data['numer'])) {
            $rok = date('Y');
            $miesiac = date('m');
            $ostatnieId = \App\Models\Zlecenie::max('id') ?? 0;
            $noweId = $ostatnieId + 1;
            
            $data['numer'] = "ZP/{$rok}/{$miesiac}/{$noweId}";
        }
        
        return $data;
    }
}
