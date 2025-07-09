<?php

namespace App\Services;

use App\Models\Zlecenie;
use App\Models\Partia;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

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
            ->visible(fn ($record) => $record->status === 'w_realizacji')
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
    public function createPobierzSurowceAction(): Action
    {
        return Action::make('pobierz_surowce')
            ->label('Pobierz surowce')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->action(function ($record) {
                try {
                    $wyniki = $this->zlecenieService->pobierzSurowceDoZlecenia($record);
                    
                    $komunikat = "✅ Surowce zostały pobrane z magazynu!\n\nPodsumowanie:\n";
                    
                    foreach ($wyniki as $wynik) {
                        $komunikat .= "\n• {$wynik['nazwa']}: {$wynik['calkowita_masa']}kg\n";
                        
                        foreach ($wynik['pobrania'] as $pobranie) {
                            $komunikat .= "  - Partia {$pobranie['numer_partii']}: {$pobranie['masa_pobrana']}kg\n";
                        }
                    }
                    
                    Notification::make()
                        ->title('Surowce pobrane')
                        ->body($komunikat)
                        ->success()
                        ->persistent()
                        ->send();
                        
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Błąd podczas pobierania surowców')
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
            ->label('Utwórz partię')
            ->icon('heroicon-o-cube-transparent')
            ->color('success')
            ->visible(fn ($record) => $record->status === 'zrealizowane')
            ->form([
                Forms\Components\TextInput::make('numer_partii')
                    ->label('Numer partii')
                    ->default(fn () => Partia::generateNumerPartii())
                    ->required(),
                Forms\Components\TextInput::make('ilosc_wyprodukowana')
                    ->label('Ilość rzeczywiście wyprodukowana')
                    ->numeric()
                    ->required()
                    ->default(fn ($record) => $record->ilosc),
                Forms\Components\DatePicker::make('data_produkcji')
                    ->label('Data produkcji')
                    ->required()
                    ->default(now()),
                Forms\Components\DatePicker::make('data_waznosci')
                    ->label('Data ważności')
                    ->default(now()->addMonths(12)),
                Forms\Components\Textarea::make('uwagi')
                    ->label('Uwagi dotyczące produkcji'),
            ])
            ->action(function (array $data, $record) {
                try {
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