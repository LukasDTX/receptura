<?php

namespace App\Filament\Widgets;

use App\Models\Zlecenie;
use App\Models\Surowiec;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ZleceniaStats extends BaseWidget
{
    protected function getStats(): array
    {
        // Podsumowanie zleceń
        $noweZlecenia = Zlecenie::where('status', 'nowe')->count();
        $wRealizacjiZlecenia = Zlecenie::where('status', 'w_realizacji')->count();
        $wszystkieZlecenia = Zlecenie::count();
        
        // Podsumowanie surowców potrzebnych do realizacji wszystkich nowych i w realizacji zleceń
        $surowcePotrzebne = $this->getPotrzebneSurowce();
        
        // Przygotuj informację o najczęściej używanych surowcach
        $topSurowce = '';
        $licznik = 0;
        
        foreach ($surowcePotrzebne as $id => $dane) {
            if ($licznik >= 3) break; // Wyświetl tylko top 3
            
            $topSurowce .= ($licznik > 0 ? ', ' : '') . $dane['nazwa'] . ': ' . 
                number_format($dane['ilosc'], $dane['jednostka'] === 'kg' || $dane['jednostka'] === 'l' ? 3 : 0) . ' ' . 
                $dane['jednostka'];
            
            $licznik++;
        }
        
        if (empty($topSurowce)) {
            $topSurowce = 'Brak danych o potrzebnych surowcach';
        }
        
        return [
            Stat::make('Nowe zlecenia', $noweZlecenia)
                ->description('Oczekują na realizację')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
                
            Stat::make('W realizacji', $wRealizacjiZlecenia)
                ->description('Zlecenia w trakcie produkcji')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('success'),
                
            Stat::make('Wszystkie zlecenia', $wszystkieZlecenia)
                ->description('Łączna liczba zleceń w systemie')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),
                
            Stat::make('Najbardziej potrzebne surowce', $topSurowce)
                ->description('Top 3 surowce w aktywnych zleceniach')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('primary'),
        ];
    }
    
    /**
     * Oblicza sumaryczne zapotrzebowanie na surowce dla wszystkich aktywnych zleceń
     * 
     * @return array
     */
    protected function getPotrzebneSurowce(): array
    {
        // Pobierz wszystkie aktywne zlecenia (nowe i w realizacji)
        $zlecenia = Zlecenie::whereIn('status', ['nowe', 'w_realizacji'])->get();
        
        $surowcePotrzebne = [];
        
        foreach ($zlecenia as $zlecenie) {
            if (empty($zlecenie->surowce_potrzebne)) {
                continue;
            }
            
            foreach ($zlecenie->surowce_potrzebne as $surowiec) {
                // Poprawka: sprawdź czy klucz istnieje, użyj 'id' zamiast 'surowiec_id'
                $id = $surowiec['id'] ?? $surowiec['surowiec_id'] ?? null;
                
                if ($id === null) {
                    // Jeśli brak ID, pomiń ten surowiec lub użyj nazwy jako klucza
                    $id = $surowiec['nazwa'] ?? 'nieznany_' . rand(1000, 9999);
                }
                
                if (!isset($surowcePotrzebne[$id])) {
                    $surowcePotrzebne[$id] = [
                        'nazwa' => $surowiec['nazwa'] ?? 'Nieznany surowiec',
                        'kod' => $surowiec['kod'] ?? '',
                        'ilosc' => 0,
                        'jednostka' => $surowiec['jednostka'] ?? 'szt',
                        'koszt' => 0,
                    ];
                }
                
                // Jeśli jednostki są różne, spróbuj je ujednolicić
                if ($surowcePotrzebne[$id]['jednostka'] !== $surowiec['jednostka']) {
                    // Konwertuj kg na g lub l na ml
                    if ($surowcePotrzebne[$id]['jednostka'] === 'kg' && $surowiec['jednostka'] === 'g') {
                        $surowcePotrzebne[$id]['ilosc'] = $surowcePotrzebne[$id]['ilosc'] * 1000;
                        $surowcePotrzebne[$id]['jednostka'] = 'g';
                    } elseif ($surowcePotrzebne[$id]['jednostka'] === 'g' && $surowiec['jednostka'] === 'kg') {
                        $surowcePotrzebne[$id]['ilosc'] = $surowcePotrzebne[$id]['ilosc'] / 1000;
                        $surowcePotrzebne[$id]['jednostka'] = 'kg';
                    } elseif ($surowcePotrzebne[$id]['jednostka'] === 'l' && $surowiec['jednostka'] === 'ml') {
                        $surowcePotrzebne[$id]['ilosc'] = $surowcePotrzebne[$id]['ilosc'] * 1000;
                        $surowcePotrzebne[$id]['jednostka'] = 'ml';
                    } elseif ($surowcePotrzebne[$id]['jednostka'] === 'ml' && $surowiec['jednostka'] === 'l') {
                        $surowcePotrzebne[$id]['ilosc'] = $surowcePotrzebne[$id]['ilosc'] / 1000;
                        $surowcePotrzebne[$id]['jednostka'] = 'l';
                    }
                }
                
                // Dodaj ilość i koszt (z zabezpieczeniami)
                $surowcePotrzebne[$id]['ilosc'] += $surowiec['ilosc'] ?? 0;
                $surowcePotrzebne[$id]['koszt'] += $surowiec['koszt'] ?? 0;
            }
        }
        
        // Sortuj po ilości (malejąco)
        uasort($surowcePotrzebne, function ($a, $b) {
            return $b['ilosc'] <=> $a['ilosc'];
        });
        
        return $surowcePotrzebne;
    }
}