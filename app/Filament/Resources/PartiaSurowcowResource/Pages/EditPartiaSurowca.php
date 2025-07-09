<?php
// app/Filament/Resources/PartiaSurowcaResource/Pages/EditPartiaSurowca.php

namespace App\Filament\Resources\PartiaSurowcaResource\Pages;

use App\Filament\Resources\PartiaSurowcaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPartiaSurowca extends EditRecord
{
    protected static string $resource = PartiaSurowcaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === 'nowa' && $this->record->masa_pozostala == $this->record->masa_netto),
                
            Actions\Action::make('zobacz_w_magazynie_produkcji')
                ->label('Zobacz w magazynie produkcji')
                ->icon('heroicon-o-building-office')
                ->color('info')
                ->visible(fn () => $this->record->magazynProdukcji()->exists())
                ->url(fn () => '/receptura/magazyn-produkcji?partia=' . $this->record->id)
                ->openUrlInNewTab(),
                
            Actions\Action::make('przenies_do_produkcji')
                ->label('Przenieś do magazynu produkcji')
                ->icon('heroicon-o-arrow-right')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'nowa' && $this->record->masa_pozostala > 0)
                ->form([
                    \Filament\Forms\Components\TextInput::make('masa_do_przeniesienia')
                        ->label('Masa do przeniesienia (kg)')
                        ->required()
                        ->numeric()
                        ->minValue(0.001)
                        ->maxValue(fn () => $this->record->masa_pozostala)
                        ->suffix('kg')
                        ->helperText('Maksymalnie: ' . $this->record->masa_pozostala . ' kg'),
                    \Filament\Forms\Components\TextInput::make('lokalizacja_produkcji')
                        ->label('Lokalizacja w magazynie produkcji')
                        ->required()
                        ->default('PROD-' . $this->record->surowiec->kod),
                    \Filament\Forms\Components\Textarea::make('uwagi')
                        ->label('Uwagi')
                        ->placeholder('Powód przeniesienia do magazynu produkcji...'),
                ])
                ->action(function (array $data) {
                    $masaDoPrzeniesienia = $data['masa_do_przeniesienia'];
                    $masaPrzed = $this->record->masa_pozostala;
                    $masaPo = $masaPrzed - $masaDoPrzeniesienia;
                    
                    // Utwórz pozycję w magazynie produkcji
                    \App\Models\MagazynProdukcji::create([
                        'partia_surowca_id' => $this->record->id,
                        'masa_dostepna' => $masaDoPrzeniesienia,
                        'lokalizacja' => $data['lokalizacja_produkcji'],
                        'data_przeniesienia' => now(),
                    ]);
                    
                    // Utwórz ruch przeniesienia
                    \App\Models\RuchSurowca::create([
                        'typ_ruchu' => 'przeniesienie',
                        'partia_surowca_id' => $this->record->id,
                        'masa' => -$masaDoPrzeniesienia,
                        'masa_przed' => $masaPrzed,
                        'masa_po' => $masaPo,
                        'skad' => 'magazyn_glowny',
                        'dokad' => 'magazyn_produkcji',
                        'data_ruchu' => now(),
                        'uwagi' => $data['uwagi'] ?? 'Przeniesienie do magazynu produkcji',
                        'user_id' => auth()->id(),
                    ]);
                    
                    // Aktualizuj status i masę partii
                    $this->record->update([
                        'masa_pozostala' => $masaPo,
                        'status' => $masaPo > 0 ? 'otwarta' : 'zuzyta',
                        'data_otwarcia' => now(),
                    ]);
                    
                    Notification::make()
                        ->title('Przeniesienie wykonane')
                        ->body("Przeniesiono {$masaDoPrzeniesienia}kg do magazynu produkcji")
                        ->success()
                        ->send();
                        
                    // Odśwież stronę
                    return redirect()->to($this->getResource()::getUrl('edit', ['record' => $this->record]));
                })
                ->requiresConfirmation()
                ->modalHeading('Przenieś do magazynu produkcji')
                ->modalDescription('Część partii zostanie przeniesiona do magazynu produkcji.')
                ->modalSubmitActionLabel('Przenieś'),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Zapisano')
            ->body('Zmiany w partii zostały pomyślnie zapisane.');
    }
}