<?php

namespace App\Filament\Resources\OpakowanieResource\Pages;

use App\Filament\Resources\OpakowanieResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOpakowanie extends CreateRecord
{
    protected static string $resource = OpakowanieResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

