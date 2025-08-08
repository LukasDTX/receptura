<?php

namespace App\Services;

use App\Models\Zlecenie;
use App\Models\Partia;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Carbon\Carbon;
class ZlecenieActionsService
{
    public function __construct(
        private ZlecenieService $zlecenieService
    ) {}

    /**
     * Tworzy akcję sprawdzania dostępności surowców
     */
    public function createSprawdzDostepnoscAction(): Action
    {
        return Action::make('sprawdz_dostepnosc')
            ->label('Sprawdź dostępność surowców')
            ->icon('heroicon-o-magnifying-glass')
            ->color('info')
            ->action(function ($record) {
                try {
                    $analiza = $this->zlecenieService->sprawdzDostepnoscSurowcow($record);
                    
                    if ($analiza['mozliwe_do_realizacji']) {
                        $komunikat = "✅ Wszystkie surowce są dostępne w magazynie!\n\nPlan pobrania:\n";
                        
                        foreach ($analiza['plan_pobran'] as $plan) {
                            $komunikat .= "\n• {$plan['nazwa']}: {$plan['potrzebna_masa']}kg\n";
                            
                            foreach ($plan['plan_pobrania'] as $pobranie) {
                                $typ = $pobranie['typ'] === 'magazyn_produkcji' ? '📦 Mag. produkcji' : '🏪 Mag. główny';
                                $komunikat .= "  - {$typ}: {$pobranie['masa']}kg z partii {$pobranie['numer_partii']}\n";
                            }
                        }
                        
                        Notification::make()
                            ->title('Zlecenie można zrealizować')
                            ->body($komunikat)
                            ->success()
                            ->persistent()
                            ->send();
                    } else {
                        $komunikat = "❌ Braki w magazynie:\n\n";
                        
                        foreach ($analiza['braki'] as $brak) {
                            $komunikat .= "• {$brak['nazwa']}: brak {$brak['brak']}kg (dostępne: {$brak['dostepna']}kg)\n";
                        }
                        
                        Notification::make()
                            ->title('Nie można zrealizować zlecenia')
                            ->body($komunikat)
                            ->danger()
                            ->send();
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Błąd podczas sprawdzania dostępności surowców')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }        
            })
            ->visible(fn ($record) => !empty($record->surowce_potrzebne));
    }

    /**
     * Tworzy akcję przeglądania pobranych surowców
     */
    public function createZobaczPobraneSurowceAction(): Action
    {
        return Action::make('zobacz_pobrane_surowce')
            ->label('Zobacz pobrane surowce')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('info')
            ->visible(fn ($record) => $record->status === 'w_realizacji' || $record->status === 'zrealizowane')
            ->modalHeading(fn ($record) => 'Pobrane surowce - Zlecenie: ' . $record->numer)
            ->modalContent(function ($record) {
                $podsumowanie = $this->zlecenieService->getPobraneSurowce($record);

                if (empty($podsumowanie)) {
                    return view('filament.modals.empty-content', [
                        'message' => 'Nie znaleziono pobranych surowców dla tego zlecenia.'
                    ]);
                }

                return view('filament.modals.pobrane-surowce', compact('podsumowanie', 'record'));
            })
            ->modalCancelActionLabel('Zamknij')
            ->modalSubmitAction(false)
            ->modalWidth('7xl');
    }

    /**
     * Tworzy akcję eksportu pobranych surowców do PDF
     */
    public function createEksportPobranychSurowcowAction(): Action
    {
        return Action::make('eksport_pobranych_surowcow')
            ->label('Eksport PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->visible(fn ($record) => $record->status === 'w_realizacji')
            ->action(function ($record) {
                $podsumowanie = $this->zlecenieService->getPobraneSurowce($record);

                if (empty($podsumowanie)) {
                    Notification::make()
                        ->title('Brak danych')
                        ->body('Nie znaleziono pobranych surowców dla tego zlecenia.')
                        ->warning()
                        ->send();
                    return;
                }

                // Generuj PDF
                $pdf = app('dompdf.wrapper');
                $pdf->loadView('pdf.pobrane-surowce', compact('podsumowanie', 'record'));
                
                return response()->streamDownload(function () use ($pdf) {
                    echo $pdf->output();
                }, "pobrane_surowce_zlecenie_{$record->numer}.pdf");
            });
    }

    /**
     * Tworzy akcję pobierania surowców z magazynu
     */
public function createUruchomProdukcjeAction(): Action
{
    return Action::make('uruchom produkcje')
        ->label('Uruchom produkcję')
        ->icon('heroicon-o-arrow-down-tray')
        ->color('success')
        ->action(function ($record) {
            try {
                $okresEnum = $record->produkt->okres_waznosci ?? null;
                $okres = strtoupper(trim($okresEnum?->value ?? ''));

                if (empty($okres)) {
                    throw new \Exception('Brak zdefiniowanego okresu ważności w produkcie.');
                }

                $wyniki = $this->zlecenieService->pobierzSurowceDoZlecenia($record);
                if (empty($wyniki)) {
                    Notification::make()
                        ->title('Brak surowców')
                        ->body('Nie można uruchomić produkcji, ponieważ brakuje surowców w magazynie.')
                        ->danger()
                        ->persistent()
                        ->send();
                    return;
                }

                // NAJPIERW wygeneruj numer partii (PRZED aktualizacją rekordu)
                $numerPartii = Zlecenie::generateNumerPartii();

                $dataWaznosci = now();
                if (preg_match('/^(\d+)M$/', $okres, $matches)) {
                    $dataWaznosci->addMonths((int) $matches[1]);
                } elseif (preg_match('/^(\d+)D$/', $okres, $matches)) {
                    $dataWaznosci->addDays((int) $matches[1]);
                } else {
                    throw new \Exception("Nieprawidłowy format okresu ważności: {$okres}");
                }

                // Zaktualizuj rekord z wszystkimi danymi
                $record->update([
                    'data_produkcji' => now()->startOfDay(), // DODANE - ustaw datę produkcji
                    'data_waznosci' => $dataWaznosci->startOfDay(),
                    'numer_partii' => $numerPartii
                ]);

                $komunikat = "✅ Surowce zostały pobrane z magazynu!\n";
                $komunikat .= "Numer partii: {$numerPartii}\n";
                $komunikat .= "Data ważności: {$dataWaznosci->format('Y-m-d')}\n\n";
                $komunikat .= "Podsumowanie:\n";

                foreach ($wyniki as $wynik) {
                    $komunikat .= "\n• {$wynik['nazwa']}: {$wynik['calkowita_masa']}kg\n";
                    foreach ($wynik['pobrania'] as $pobranie) {
                        $komunikat .= "  - Partia {$pobranie['numer_partii']}: {$pobranie['masa_pobrana']}kg\n";
                    }
                }

                Notification::make()
                    ->title('Produkcja uruchomiona')
                    ->body($komunikat)
                    ->success()
                    ->persistent()
                    ->send();

            } catch (\Exception $e) {
                Notification::make()
                    ->title('Błąd podczas uruchamiania produkcji')
                    ->body($e->getMessage())
                    ->danger()
                    ->persistent()
                    ->send();
            }
        })
        ->requiresConfirmation()
        ->modalHeading('Pobierz surowce do zlecenia')
        ->modalDescription('Czy na pewno chcesz pobrać surowce z magazynu do tego zlecenia? Ta akcja spowoduje wydanie surowców z magazynu.')
        ->modalSubmitActionLabel('Pobierz surowce')
        ->visible(fn ($record) => $record->status === 'nowe' && !empty($record->surowce_potrzebne));
}





    /**
     * Tworzy akcję tworzenia partii z zlecenia
     */
public function createUtworzPartieAction(): Action
{
    return Action::make('utworz_partie')
        ->label('Wyślij na magazyn')
        ->icon('heroicon-o-cube-transparent')
        ->color('success')
        ->visible(fn ($record) => $record->status === 'zrealizowane' && !empty($record->numer_partii)) // Sprawdź czy ma numer partii
        ->form([
            Forms\Components\TextInput::make('numer_partii')
                ->label('Numer partii')
                ->readOnly()
                ->default(fn ($record) => $record->numer_partii)
                ->required(),
            Forms\Components\TextInput::make('ilosc_wyprodukowana')
                ->label('Ilość rzeczywiście wyprodukowana')
                ->numeric()
                ->required()
                ->default(fn ($record) => $record->ilosc),
            Forms\Components\DatePicker::make('data_produkcji')
                ->label('Data produkcji')
                ->required()
                ->default(fn ($record) => $record->data_produkcji ?? now()),
            Forms\Components\DatePicker::make('data_waznosci')
                ->label('Data ważności')
                ->default(fn ($record) => $record->data_waznosci ?? now()->addMonths(12))
                ->readOnly(),
            Forms\Components\Textarea::make('uwagi')
                ->label('Uwagi dotyczące produkcji'),
        ])
        ->action(function (array $data, $record) {
            try {
                // Dodatkowa walidacja w akcji
                if (empty($record->numer_partii)) {
                    throw new \Exception('Zlecenie nie ma wygenerowanego numeru partii. Najpierw uruchom produkcję.');
                }
                
                $partia = $this->zlecenieService->utworzPartieZZlecenia($record, $data);
                
                Notification::make()
                    ->title('Partia utworzona')
                    ->body("Partia {$partia->numer_partii} została utworzona i dodana do magazynu.")
                    ->success()
                    ->send();
                    
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Błąd podczas tworzenia partii')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        })
        ->requiresConfirmation()
        ->modalHeading('Utwórz partię produktu')
        ->modalDescription('Zostanie utworzona nowa partia i dodana do magazynu.')
        ->modalSubmitActionLabel('Utwórz partię');
}

    /**
     * Tworzy akcję zmiany statusu (bulk action)
     */
    public function createZmienStatusBulkAction(): \Filament\Tables\Actions\BulkAction
    {
        return \Filament\Tables\Actions\BulkAction::make('zmien_status')
            ->label('Zmień status')
            ->icon('heroicon-o-pencil-square')
            ->form([
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'nowe' => 'Nowe',
                        'w_realizacji' => 'W realizacji',
                        'zrealizowane' => 'Zrealizowane',
                        'anulowane' => 'Anulowane',
                    ])
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                foreach ($records as $record) {
                    $record->update([
                        'status' => $data['status'],
                    ]);
                }
                
                Notification::make()
                    ->title('Status zaktualizowany')
                    ->body('Status został zmieniony dla ' . $records->count() . ' zleceń.')
                    ->success()
                    ->send();
            });
    }
}