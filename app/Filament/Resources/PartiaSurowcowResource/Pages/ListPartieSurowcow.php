<?php
// app/Filament/Resources/PartiaSurowcaResource/Pages/ListPartieSurowcow.php

namespace App\Filament\Resources\PartiaSurowcaResource\Pages;

use App\Filament\Resources\PartiaSurowcaResource;
use App\Services\MagazynSurowcowService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Notifications\Notification;

class ListPartieSurowcow extends ListRecords
{
    protected static string $resource = PartiaSurowcaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj partię surowca')
                ->icon('heroicon-o-plus')
                ->color('success'),
                
            Actions\Action::make('raport_stanu')
                ->label('Raport stanu magazynu')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->action(function () {
                    $service = new MagazynSurowcowService();
                    $raport = $service->generujRaportStanu();
                    
                    // Można tu przekierować do specjalnej strony raportu
                    // lub wyświetlić w modalu
                    session(['raport_magazynu' => $raport]);
                    
                    Notification::make()
                        ->title('Raport wygenerowany')
                        ->body('Raport stanu magazynu został wygenerowany.')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('partie_przeterminowane')
                ->label('Sprawdź terminy ważności')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->badge(function () {
                    $service = new MagazynSurowcowService();
                    return $service->znajdzPartieWkrotcePrzeterminowane(30)->count();
                })
                ->action(function () {
                    $service = new MagazynSurowcowService();
                    $partie = $service->znajdzPartieWkrotcePrzeterminowane(30);
                    
                    if ($partie->isEmpty()) {
                        Notification::make()
                            ->title('Brak zagrożeń')
                            ->body('Wszystkie partie mają aktualny termin ważności.')
                            ->success()
                            ->send();
                    } else {
                        $komunikat = "Znaleziono {$partie->count()} parti wkrótce przeterminowanych:\n";
                        foreach ($partie->take(5) as $partia) {
                            $komunikat .= "• {$partia->surowiec->nazwa} ({$partia->numer_partii}) - ważne do {$partia->data_waznosci->format('d.m.Y')}\n";
                        }
                        
                        if ($partie->count() > 5) {
                            $komunikat .= "... i " . ($partie->count() - 5) . " więcej";
                        }
                        
                        Notification::make()
                            ->title('Ostrzeżenie o terminach ważności')
                            ->body($komunikat)
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                }),
                
            // IMPORT PARTI Z EXCEL
            Actions\Action::make('import_partie')
                ->label('Importuj partie z Excel')
                ->icon('heroicon-o-document-arrow-up')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Plik Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->visibility('private'),
                        
                    Forms\Components\Textarea::make('instrukcje')
                        ->label('Format pliku')
                        ->default('Kolumny w pliku Excel:
A: Kod surowca (musi istnieć w bazie)
B: Numer partii dostawcy
C: Masa brutto (kg)
D: Masa netto (kg)
E: Typ opakowania (worek_25kg, big_bag, itp.)
F: Cena za kg (PLN)
G: Data ważności (YYYY-MM-DD lub puste)
H: Lokalizacja w magazynie
I: Uwagi

Pierwszy wiersz z nagłówkami zostanie pominięty.')
                        ->disabled()
                        ->rows(8)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    // Tutaj implementacja importu
                    // Podobnie jak w SurowiecResource, ale dla parti
                })
                ->modalHeading('Import parti surowców')
                ->modalWidth('xl'),
        ];
    }
}

