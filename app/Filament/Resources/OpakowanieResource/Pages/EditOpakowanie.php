<?php

namespace App\Filament\Resources\OpakowanieResource\Pages;

use App\Filament\Resources\OpakowanieResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOpakowanie extends EditRecord
{
    protected static string $resource = OpakowanieResource::class;

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
