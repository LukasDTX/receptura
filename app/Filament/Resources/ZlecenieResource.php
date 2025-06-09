<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZlecenieResource\Pages;
use App\Models\Zlecenie;
use App\Models\Produkt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Collection;

class ZlecenieResource extends Resource
{
    protected static ?string $model = Zlecenie::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    
    protected static ?string $navigationLabel = 'Zlecenia produkcyjne';
    
    protected static ?string $modelLabel = 'Zlecenie';
    
    protected static ?string $pluralModelLabel = 'Zlecenia';
    
    protected static ?string $navigationGroup = 'Produkcja';
    
    protected static ?int $navigationSort = 100;
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'nowe')->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'nowe')->count() > 0 ? 'warning' : 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('numer')
                    ->label('Numer zlecenia')
                    ->default(function () {
                        $rok = date('Y');
                        $miesiac = date('m');
                        $ostatnieId = \App\Models\Zlecenie::max('id') ?? 0;
                        $noweId = $ostatnieId + 1;
                        
                        return "ZP/{$rok}/{$miesiac}/{$noweId}";
                    })
                    ->required()
                    ->maxLength(255)
                    ->unique(ignorable: fn ($record) => $record),
                
                Forms\Components\Grid::make()
                    ->schema([
                        Select::make('produkt_id')
                            ->label('Produkt')
                            ->options(function () {
                                return Produkt::all()->pluck('nazwa', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $set('ilosc', 1);
                                $set('ilosc_zmieniona', false);
                                $set('surowce_przeliczone', false);
                                session()->forget('temp_surowce_potrzebne');
                            }),
                            
                        Forms\Components\TextInput::make('ilosc')
                            ->label('Ilość (sztuk)')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get, $state, $old) {
                                if ($old !== null && $old != $state) {
                                    $set('ilosc_zmieniona', true);
                                    $set('surowce_przeliczone', false); // Reset po zmianie ilości
                                }
                            })
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('przelicz_surowce')
                                    ->label('')
                                    ->tooltip(function (Get $get) {
                                        $iloscZmieniona = $get('ilosc_zmieniona');
                                        if ($iloscZmieniona === true || $iloscZmieniona === 'true') {
                                            return 'Przelicz surowce (ilość została zmieniona)';
                                        }
                                        return 'Przelicz surowce';
                                    })
                                    ->icon('heroicon-o-calculator')
                                    ->color(function (Get $get) {
                                        $iloscZmieniona = $get('ilosc_zmieniona');
                                        if ($iloscZmieniona === true || $iloscZmieniona === 'true') {
                                            return 'danger';
                                        }
                                        return 'warning';
                                    })
                                    ->visible(fn (Get $get) => $get('produkt_id') !== null)
->action(function (Set $set, Get $get, $record, $livewire) {
    $produktId = $get('produkt_id');
    $ilosc = $get('ilosc');
    
    // Debug logging
    \Illuminate\Support\Facades\Log::info('Przelicz surowce - start', [
        'produkt_id' => $produktId,
        'ilosc' => $ilosc,
        'record_exists' => $record ? 'tak' : 'nie'
    ]);
    
    if ($ilosc === null || $ilosc === '' || !is_numeric($ilosc)) {
        \Filament\Notifications\Notification::make()
            ->title('Błąd')
            ->body('Podaj prawidłową ilość produktów.')
            ->danger()
            ->send();
        return;
    }
    
    $ilosc = (int) $ilosc;
    
    if ($ilosc <= 0) {
        \Filament\Notifications\Notification::make()
            ->title('Błąd')
            ->body('Ilość musi być większa od 0.')
            ->danger()
            ->send();
        return;
    }
    
    if (!$produktId) {
        \Filament\Notifications\Notification::make()
            ->title('Błąd')
            ->body('Wybierz produkt przed przeliczeniem surowców.')
            ->danger()
            ->send();
        return;
    }
    
    $produkt = \App\Models\Produkt::with(['receptura.surowce', 'opakowanie'])->find($produktId);
    
    if (!$produkt || !$produkt->receptura) {
        \Filament\Notifications\Notification::make()
            ->title('Błąd')
            ->body('Produkt nie ma przypisanej receptury.')
            ->danger()
            ->send();
        return;
    }
    
    $surowcePotrzebne = [];
    
    // POPRAWIONE OBLICZENIA - uwzględniamy typ receptury
    $receptura = $produkt->receptura;
    $opakowanie = $produkt->opakowanie;
    
    // Sprawdź kompatybilność opakowania z recepturą
    $typReceptury = $receptura->typ_receptury ?? \App\Enums\TypReceptury::GRAMY;
    $jednostkaOpakowania = $opakowanie->jednostka instanceof \App\Enums\JednostkaOpakowania 
        ? $opakowanie->jednostka->value 
        : $opakowanie->jednostka;
        
    // Sprawdź zgodność typów
    $kompatybilne = ($typReceptury === \App\Enums\TypReceptury::GRAMY && $jednostkaOpakowania === 'g') ||
                   ($typReceptury === \App\Enums\TypReceptury::MILILITRY && $jednostkaOpakowania === 'ml');
                   
    if (!$kompatybilne) {
        \Filament\Notifications\Notification::make()
            ->title('Błąd kompatybilności')
            ->body("Receptura typu '{$typReceptury->value}' nie jest kompatybilna z opakowaniem typu '{$jednostkaOpakowania}'.")
            ->danger()
            ->send();
        return;
    }
    
    // Oblicz współczynnik skalowania
    // Receptura zawsze dla 1000 jednostek bazowych (1kg = 1000g lub 1l = 1000ml)
    $pojemnoscBazowa = (float) ($opakowanie->pojemnosc ?? 0); // np. 250g lub 250ml
    $wspolczynnikSkalowania = $pojemnoscBazowa / 1000; // 250/1000 = 0,25
    
    \Illuminate\Support\Facades\Log::info('Parametry obliczenia', [
        'typ_receptury' => $typReceptury->value,
        'jednostka_opakowania' => $jednostkaOpakowania,
        'pojemnosc_opakowania' => $pojemnoscBazowa,
        'wspolczynnik_skalowania' => $wspolczynnikSkalowania,
        'ilosc_produktow' => $ilosc
    ]);
    
    // Oblicz surowce z receptury
    foreach ($receptura->surowce as $surowiec) {
        $iloscWRecepturze = (float) ($surowiec->pivot->ilosc ?? 0); // Ilość na 1000 jednostek bazowych (1kg lub 1l)
        
        // Przelicz na jedno opakowanie
        $iloscNaJednoOpakowanie = $iloscWRecepturze * $wspolczynnikSkalowania;
        
        // Przelicz na całe zlecenie
        $iloscNaZlecenie = $iloscNaJednoOpakowanie * $ilosc;
        
        // Pobierz cenę surowca
        $cenaSurowca = (float) ($surowiec->cena_jednostkowa ?? 0);
        
        // Fallback ceny jeśli nie ma ustawionej
        if ($cenaSurowca == 0) {
            $cenySurowcow = [
                'kolagen rybi' => 0.050,
                'proszek ananas' => 0.030,
                'vit d' => 0.200,
                'witamina d' => 0.200,
                'kolagen' => 0.050,
                'ananas' => 0.030,
            ];
            
            $nazwaNormalizowana = strtolower($surowiec->nazwa ?? '');
            foreach ($cenySurowcow as $nazwa => $cena) {
                if (str_contains($nazwaNormalizowana, $nazwa)) {
                    $cenaSurowca = (float) $cena;
                    break;
                }
            }
        }
        
        // Konwersja enum na string dla jednostki
        $jednostka = $surowiec->jednostka_miary;
        if ($jednostka instanceof \App\Enums\JednostkaMiary) {
            $jednostka = $jednostka->value;
        } else {
            $jednostka = $jednostka ?? 'g';
        }
        
        // ZAWSZE używaj jednostek bazowych (g, ml) - bez konwersji na kg/l
        $iloscDoWyswietlenia = $iloscNaZlecenie;
        $jednostkaDoWyswietlenia = $jednostka;
        
        $kostSurowca = $iloscNaZlecenie * $cenaSurowca;
        
        \Illuminate\Support\Facades\Log::info('Obliczenie surowca', [
            'nazwa' => $surowiec->nazwa,
            'ilosc_w_recepturze' => $iloscWRecepturze,
            'ilosc_na_opakowanie' => $iloscNaJednoOpakowanie,
            'ilosc_na_zlecenie' => $iloscNaZlecenie,
            'ilosc_do_wyswietlenia' => $iloscDoWyswietlenia,
            'jednostka_oryginalna' => $jednostka,
            'jednostka_wyswietlenia' => $jednostkaDoWyswietlenia,
            'cena_jednostkowa' => $cenaSurowca,
            'koszt' => $kostSurowca
        ]);
        
        $surowcePotrzebne[] = [
            'id' => $surowiec->id,
            'surowiec_id' => $surowiec->id,
            'nazwa' => $surowiec->nazwa ?? 'Nieznany surowiec',
            'kod' => $surowiec->kod ?? 'SR-' . $surowiec->id,
            'ilosc' => $iloscDoWyswietlenia,
            'jednostka' => $jednostkaDoWyswietlenia,
            'cena_jednostkowa' => $cenaSurowca,
            'koszt' => $kostSurowca,
        ];
    }
    
    // Dodaj opakowania do surowców
if ($opakowanie) {
    $cenaOpakowania = (float) ($opakowanie->cena ?? 0);
    
    // DEBUG - sprawdź wartości
    \Illuminate\Support\Facades\Log::info('Obliczenie opakowania', [
        'nazwa_opakowania' => $opakowanie->nazwa,
        'ilosc_produktow' => $ilosc,
        'cena_opakowania' => $cenaOpakowania,
        'koszt_calkowity_opakowania' => $ilosc * $cenaOpakowania
    ]);
    
    $surowcePotrzebne[] = [
        'id' => 'opakowanie_' . $opakowanie->id,
        'surowiec_id' => 'opakowanie_' . $opakowanie->id,
        'nazwa' => $opakowanie->nazwa ?? 'Nieznane opakowanie',
        'kod' => $opakowanie->kod ?? 'OP-' . $opakowanie->id,
        'ilosc' => $ilosc, // Ta wartość to ilość produktów = ilość opakowań
        'jednostka' => 'szt',
        'cena_jednostkowa' => $cenaOpakowania,
        'koszt' => $ilosc * $cenaOpakowania,
    ];
}
    
    // Debug - sprawdź czy są surowce
    \Illuminate\Support\Facades\Log::info('Obliczone surowce', [
        'count' => count($surowcePotrzebne),
        'surowce' => $surowcePotrzebne
    ]);
    
    if ($record) {
        $record->update([
            'surowce_potrzebne' => $surowcePotrzebne,
            'ilosc' => $ilosc,
        ]);
        
        \Filament\Notifications\Notification::make()
            ->title('Sukces')
            ->body('Surowce zostały przeliczone i zapisane.')
            ->success()
            ->send();
        
        redirect(request()->header('Referer'));
    } else {
        // Zapisz do sesji z unikalnym kluczem
        $sessionKey = 'temp_surowce_potrzebne_' . uniqid();
        session([$sessionKey => $surowcePotrzebne]);
        session(['temp_surowce_potrzebne' => $surowcePotrzebne]);
        
        // Debug sesji
        \Illuminate\Support\Facades\Log::info('Zapisano do sesji', [
            'session_key' => $sessionKey,
            'session_data' => session('temp_surowce_potrzebne'),
            'session_all_keys' => array_keys(session()->all())
        ]);
        
        $set('surowce_przeliczone', true);
        
        \Filament\Notifications\Notification::make()
            ->title('Sukces')
            ->body('Surowce zostały przeliczone. Teraz możesz zapisać zlecenie.')
            ->success()
            ->send();
    }
    
    $set('ilosc_zmieniona', false);
})
                                    ->requiresConfirmation()
                                    ->modalHeading('Przelicz surowce')
                                    ->modalDescription('Czy na pewno chcesz przeliczyć surowce dla aktualnej ilości produktów?')
                                    ->modalSubmitActionLabel('Przelicz')
                                    ->modalCancelActionLabel('Anuluj')
                            ),
                        
                        Forms\Components\DatePicker::make('data_zlecenia')
                            ->label('Data zlecenia')
                            ->required()
                            ->default(now()),
                            
                        Forms\Components\DatePicker::make('planowana_data_realizacji')
                            ->label('Planowana data realizacji')
                            ->minDate(fn (Get $get) => $get('data_zlecenia'))
                            ->default(now()->addDays(7)),
                        
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'nowe' => 'Nowe',
                                'w_realizacji' => 'W realizacji',
                                'zrealizowane' => 'Zrealizowane',
                                'anulowane' => 'Anulowane',
                            ])
                            ->default('nowe')
                            ->required(),
                    ])
                    ->columns(2),
                
                Forms\Components\Textarea::make('uwagi')
                    ->label('Uwagi')
                    ->columnSpanFull(),

                // Status indicator
                Forms\Components\Placeholder::make('status_indicator')
                    ->label('')
                    ->content(function ($record, Get $get) {
                        if ($record) return '';
                        
                        $produktId = $get('produkt_id');
                        $ilosc = $get('ilosc');
                        $surowcePreeliczone = $get('surowce_przeliczone');
                        $tempSurowce = session('temp_surowce_potrzebne');
                        
                        if (!$produktId) {
                            return new \Illuminate\Support\HtmlString(
                                '<div style="padding: 8px 12px; background-color: #f9fafb; border: 1px solid #d1d5db; border-radius: 6px; color: #374151;">
                                    ℹ️ Wybierz produkt aby rozpocząć
                                </div>'
                            );
                        }
                        
                        if (!$ilosc || $ilosc <= 0) {
                            return new \Illuminate\Support\HtmlString(
                                '<div style="padding: 8px 12px; background-color: #f9fafb; border: 1px solid #d1d5db; border-radius: 6px; color: #374151;">
                                    ℹ️ Ustaw ilość produktów
                                </div>'
                            );
                        }
                        
                        if (($surowcePreeliczone === true || $surowcePreeliczone === 'true') && !empty($tempSurowce)) {
                            return new \Illuminate\Support\HtmlString(
                                '<div style="padding: 8px 12px; background-color: #d1fae5; border: 1px solid #10b981; border-radius: 6px; color: #065f46;">
                                    ✅ Gotowe do zapisu
                                </div>'
                            );
                        } else {
                            return new \Illuminate\Support\HtmlString(
                                '<div style="padding: 8px 12px; background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; color: #92400e;">
                                    ⏳ Kliknij przycisk kalkulatora przy polu "Ilość" aby przeliczyć surowce
                                </div>'
                            );
                        }
                    })
                    ->reactive()
                    ->live()
                    ->columnSpanFull(),

                // Sekcja surowców
                Forms\Components\Section::make('Surowce potrzebne do realizacji zlecenia')
                    ->description('Lista surowców potrzebnych do realizacji zlecenia.')
                    ->schema([
                        Forms\Components\Placeholder::make('surowce_info')
                            ->label('')
                            ->content(function ($record, Get $get) {
                                $surowce = null;
                                if (!$record) {
                                    $surowce = session('temp_surowce_potrzebne');
                                } else {
                                    $freshRecord = \App\Models\Zlecenie::find($record->id);
                                    $surowce = $freshRecord ? $freshRecord->surowce_potrzebne : $record->surowce_potrzebne;
                                }
                                
                                if (!$surowce || empty($surowce)) {
                                    return '<div style="padding: 20px; text-align: center; color: #6b7280;">
                                            <p>Lista surowców zostanie wygenerowana po przeliczeniu.</p>
                                            <p><small>Wybierz produkt, ustaw ilość i kliknij przycisk kalkulatora przy polu "Ilość".</small></p>
                                            </div>';
                                }
                                
                                $html = '<div style="overflow-x: auto;">';
                                $html .= '<table class="w-full text-left border-collapse border border-gray-300">';
                                $html .= '<thead>';
                                $html .= '<tr style="background-color: #f9fafb;">';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Nazwa</th>';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Kod</th>';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Ilość</th>';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Cena jedn. (PLN/g)</th>';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Koszt</th>';
                                $html .= '</tr>';
                                $html .= '</thead>';
                                $html .= '<tbody>';
                                
                                $suma = 0;
                                
foreach ($surowce as $surowiec) {
    $html .= '<tr style="border-bottom: 1px solid #e5e7eb;">';
    $html .= '<td class="py-2 px-4">' . htmlspecialchars($surowiec['nazwa']) . '</td>';
    $html .= '<td class="py-2 px-4">' . htmlspecialchars($surowiec['kod']) . '</td>';
    
    // POPRAWIONE FORMATOWANIE ILOŚCI
    $ilosc = (float) $surowiec['ilosc'];
                                    $jednostka = $surowiec['jednostka'] ?? '';
                                
    // Specjalne formatowanie dla sztuk (opakowania)
    if ($jednostka === 'szt' || $jednostka === 'ml') {
        $iloscFormatowana = number_format($ilosc, 0, ',', ' '); // Format: 1 000 szt
    } else {
        // Formatowanie dla surowców (g, ml, kg, l)
        if ($ilosc < 0.001) {
            // Bardzo małe wartości - pokaż z dokładnością do 6 miejsc
            $iloscFormatowana = number_format($ilosc, 6, ',', '');
        } elseif ($ilosc < 1) {
            // Małe wartości - pokaż z dokładnością do 3 miejsc
            $iloscFormatowana = number_format($ilosc, 3, ',', '');
        } elseif ($ilosc == intval($ilosc)) {
            // Liczby całkowite - bez miejsc po przecinku
            $iloscFormatowana = number_format($ilosc, 0, ',', '');
        } else {
            // Inne wartości - 1 miejsce po przecinku
            $iloscFormatowana = number_format($ilosc, 1, ',', '');
        }
    
    
    // Usuń zbędne zera z końca (opcjonalnie)
    $iloscFormatowana = rtrim($iloscFormatowana, '0');
    $iloscFormatowana = rtrim($iloscFormatowana, '.');
    }
    $html .= '<td class="py-2 px-4">' . $iloscFormatowana . ' ' . htmlspecialchars($surowiec['jednostka']) . '</td>';
    $html .= '<td class="py-2 px-4">' . number_format($surowiec['cena_jednostkowa'], 3) . ' PLN</td>';
    $html .= '<td class="py-2 px-4 font-semibold">' . number_format($surowiec['koszt'], 2) . ' PLN</td>';
    $html .= '</tr>';
    
    $suma += $surowiec['koszt'];
}
                                
                                $html .= '</tbody>';
                                $html .= '<tfoot>';
                                $html .= '<tr style="background-color: #f3f4f6; font-weight: bold;">';
                                $html .= '<td colspan="4" class="py-3 px-4 text-right">SUMA KOSZTÓW:</td>';
                                $html .= '<td class="py-3 px-4 text-lg">' . number_format($suma, 2) . ' PLN</td>';
                                $html .= '</tr>';
                                $html .= '</tfoot>';
                                $html .= '</table>';
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->reactive()
                            ->live(),
                    ])
                    ->collapsed(function ($record, Get $get) {
                        if (!$record) {
                            $surowcePreeliczone = $get('surowce_przeliczone');
                            $tempSurowce = session('temp_surowce_potrzebne');
                            return !($surowcePreeliczone && !empty($tempSurowce));
                        }
                        return false;
                    })
                    ->collapsible()
                    ->visible(function ($record, Get $get) {
                        if ($record) {
                            return true;
                        }
                        
                        $surowcePreeliczone = $get('surowce_przeliczone');
                        $tempSurowce = session('temp_surowce_potrzebne');
                        
                        return ($surowcePreeliczone === true || $surowcePreeliczone === 'true') && 
                               !empty($tempSurowce) && 
                               is_array($tempSurowce) && 
                               count($tempSurowce) > 0;
                    })
                    ->columnSpanFull(),

                // Informacje o produkcie
                Forms\Components\Section::make('Informacje o produkcie')
                    ->description('Szczegóły wybranego produktu i obliczenia kosztów')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('info_produkt_podstawowe')
                                    ->label('Podstawowe informacje')
                                    ->content(function (Get $get) {
                                        $produktId = $get('produkt_id');
                                        if (!$produktId) {
                                            return 'Wybierz produkt, aby zobaczyć szczegóły.';
                                        }
                                        
                                        $produkt = Produkt::with(['receptura', 'opakowanie'])->find($produktId);
                                        if (!$produkt) {
                                            return 'Nie znaleziono produktu.';
                                        }
                                        
                                        $info = "<strong>Nazwa:</strong> {$produkt->nazwa}<br>";
                                        $info .= "<strong>Kod:</strong> {$produkt->kod}<br>";
                                        
                                        if ($produkt->receptura) {
                                            $info .= "<strong>Receptura:</strong> {$produkt->receptura->nazwa}<br>";
                                            $info .= "<strong>Koszt receptury za kg:</strong> " . number_format($produkt->receptura->koszt_calkowity, 2) . " PLN/kg<br>";
                                        }
                                        
                                        if ($produkt->opakowanie) {
                                            $info .= "<strong>Opakowanie:</strong> {$produkt->opakowanie->nazwa}<br>";
                                            $info .= "<strong>Pojemność:</strong> " . number_format($produkt->opakowanie->pojemnosc, 0) . " g<br>";
                                            $info .= "<strong>Koszt opakowania:</strong> " . number_format($produkt->opakowanie->cena ?? 0, 2) . " PLN<br>";
                                        }
                                        
                                        return new \Illuminate\Support\HtmlString($info);
                                    }),
                                
                                Forms\Components\Placeholder::make('obliczenia_kosztow')
                                    ->label('Obliczenia kosztów')
                                    ->content(function (Get $get) {
                                        $produktId = $get('produkt_id');
                                        $ilosc = $get('ilosc');
                                        
                                        if ($ilosc === null || $ilosc === '' || !is_numeric($ilosc)) {
                                            $ilosc = 0;
                                        } else {
                                            $ilosc = (int) $ilosc;
                                        }
                                        
                                        if (!$produktId) {
                                            return 'Wybierz produkt, aby zobaczyć obliczenia.';
                                        }
                                        
                                        if ($ilosc <= 0) {
                                            return 'Ustaw ilość większą od 0, aby zobaczyć obliczenia.';
                                        }
                                        
                                        $produkt = Produkt::with(['receptura', 'opakowanie'])->find($produktId);
                                        if (!$produkt) {
                                            return 'Nie znaleziono produktu.';
                                        }
                                        
                                        $kosztRecepturyZaKg = $produkt->receptura ? (float) $produkt->receptura->koszt_calkowity : 0.0;
                                        $kosztOpakowania = $produkt->opakowanie ? (float) ($produkt->opakowanie->cena ?? 0) : 0.0;
                                        $pojemnoscOpakowania = $produkt->opakowanie ? (float) $produkt->opakowanie->pojemnosc : 0.0;
                                        
                                        $kosztRecepturyNaOpakowanie = 0.0;
                                        if ($pojemnoscOpakowania > 0) {
                                            $kosztRecepturyNaOpakowanie = $kosztRecepturyZaKg * ($pojemnoscOpakowania / 1000);
                                        }
                                        
                                        $kosztCalkowity1Sztuki = $kosztRecepturyNaOpakowanie + $kosztOpakowania;
                                        $kosztCalkowitiegoZlecenia = $kosztCalkowity1Sztuki * $ilosc;
                                        
                                        $info = "<strong>Na 1 sztukę:</strong><br>";
                                        $info .= "Koszt receptury: " . number_format($kosztRecepturyNaOpakowanie, 2) . " PLN<br>";
                                        $info .= "Koszt opakowania: " . number_format($kosztOpakowania, 2) . " PLN<br>";
                                        $info .= "<strong>Koszt całkowity:</strong> " . number_format($kosztCalkowity1Sztuki, 2) . " PLN<br>";
                                        
                                        $info .= "<hr style='margin: 8px 0; border: 1px solid #e5e7eb;'>";
                                        $info .= "<strong>Na {$ilosc} szt.:</strong><br>";
                                        $info .= "Koszt całkowity zlecenia: " . number_format($kosztCalkowitiegoZlecenia, 2) . " PLN<br>";
                                        
                                        if ($produkt->cena_sprzedazy > 0) {
                                            $wartoscSprzedazy = (float) $produkt->cena_sprzedazy * $ilosc;
                                            $marza = $wartoscSprzedazy - $kosztCalkowitiegoZlecenia;
                                            $marzaProcent = ($kosztCalkowitiegoZlecenia > 0) ? (($marza / $kosztCalkowitiegoZlecenia) * 100) : 0;
                                            
                                            $info .= "Wartość sprzedaży: " . number_format($wartoscSprzedazy, 2) . " PLN<br>";
                                            $info .= "Marża: " . number_format($marza, 2) . " PLN (" . number_format($marzaProcent, 2) . "%)";
                                        }
                                        
                                        return new \Illuminate\Support\HtmlString($info);
                                    }),
                            ]),
                    ])
                    ->extraAttributes([
                        'style' => 'background-color: #fefce8; border: 1px solid #fde047; border-radius: 0.5rem;'
                    ])
                    ->visible(function ($record, Get $get) {
                        if ($record) {
                            return true;
                        }
                        
                        $produktId = $get('produkt_id');
                        return !empty($produktId);
                    })
                    ->columnSpanFull(),

                // Ukryte pola
                Forms\Components\Hidden::make('ilosc_zmieniona')
                    ->default(false)
                    ->reactive(),

                Forms\Components\Hidden::make('surowce_przeliczone')
                    ->default(false)
                    ->reactive(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numer')
                    ->label('Numer zlecenia')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('produkt.nazwa')
                    ->label('Produkt')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ilosc')
                    ->label('Ilość (szt.)')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_zlecenia')
                    ->label('Data zlecenia')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('planowana_data_realizacji')
                    ->label('Planowana realizacja')
                    ->date()
                    ->sortable(),
                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'nowe' => 'Nowe',
                        'w_realizacji' => 'W realizacji',
                        'zrealizowane' => 'Zrealizowane',
                        'anulowane' => 'Anulowane',
                    ])
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'nowe' => 'Nowe',
                        'w_realizacji' => 'W realizacji',
                        'zrealizowane' => 'Zrealizowane',
                        'anulowane' => 'Anulowane',
                    ])
                    ->label('Status'),
                Tables\Filters\Filter::make('data_zlecenia')
                    ->form([
                        Forms\Components\DatePicker::make('data_od')
                            ->label('Od daty'),
                        Forms\Components\DatePicker::make('data_do')
                            ->label('Do daty'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['data_od'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data_zlecenia', '>=', $date),
                            )
                            ->when(
                                $data['data_do'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data_zlecenia', '<=', $date),
                            );
                    })
                    ->label('Data zlecenia'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edytuj')
                    ->icon('heroicon-o-pencil'),
                Tables\Actions\Action::make('drukuj')
                    ->label('Drukuj')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Zlecenie $record): string => route('zlecenie.drukuj', $record))
                    ->openUrlInNewTab(),
Tables\Actions\Action::make('utworz_partie')
                    ->label('Utwórz partię')
                    ->icon('heroicon-o-cube-transparent')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'zrealizowane')
                    ->form([
                        Forms\Components\TextInput::make('numer_partii')
                            ->label('Numer partii')
                            ->default(fn () => \App\Models\Partia::generateNumerPartii())
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
                            $partia = \App\Models\Partia::createFromZlecenie($record, $data);
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Partia utworzona')
                                ->body("Partia {$partia->numer_partii} została utworzona i dodana do magazynu.")
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Błąd podczas tworzenia partii')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Utwórz partię produktu')
                    ->modalDescription('Zostanie utworzona nowa partia i dodana do magazynu.')
                    ->modalSubmitActionLabel('Utwórz partię'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('zmien_status')
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
                        }),
                ]),
            ]);
    } 
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListZlecenies::route('/'),
            'create' => Pages\CreateZlecenie::route('/create'),
            'edit' => Pages\EditZlecenie::route('/{record}/edit'),
        ];
    }    
}