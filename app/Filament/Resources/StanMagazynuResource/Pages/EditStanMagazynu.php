<?php

namespace App\Filament\Resources\StanMagazynuResource\Pages;

use App\Filament\Resources\StanMagazynuResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStanMagazynu extends \Filament\Resources\Pages\EditRecord
{
    protected static string $resource = StanMagazynuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
