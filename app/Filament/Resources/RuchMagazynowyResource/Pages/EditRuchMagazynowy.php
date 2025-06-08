<?php

namespace App\Filament\Resources\RuchMagazynowyResource\Pages;

use App\Filament\Resources\RuchMagazynowyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

// EditRuchMagazynowy.php
class EditRuchMagazynowy extends \Filament\Resources\Pages\EditRecord
{
    protected static string $resource = RuchMagazynowyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function afterSave(): void
    {
        // Aktualizuj stan magazynu po edycji ruchu
        $this->record->aktualizujStanMagazynu();
    }
}
