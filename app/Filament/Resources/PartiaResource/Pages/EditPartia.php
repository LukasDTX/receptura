<?php

namespace App\Filament\Resources\PartiaResource\Pages;

use App\Filament\Resources\PartiaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditPartia extends \Filament\Resources\Pages\EditRecord
{
    protected static string $resource = PartiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
