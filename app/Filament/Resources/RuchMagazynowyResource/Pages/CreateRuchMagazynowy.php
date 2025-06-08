<?php

namespace App\Filament\Resources\RuchMagazynowyResource\Pages;

use App\Filament\Resources\RuchMagazynowyResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
class CreateRuchMagazynowy extends \Filament\Resources\Pages\CreateRecord
{
    protected static string $resource = RuchMagazynowyResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }
    
    protected function afterCreate(): void
    {
        // Aktualizuj stan magazynu po utworzeniu ruchu
        $this->record->aktualizujStanMagazynu();
    }
}
