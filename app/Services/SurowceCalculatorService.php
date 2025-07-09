<?php

namespace App\Services;

use App\Models\Produkt;
use App\Enums\TypReceptury;
use App\Enums\JednostkaOpakowania;
use App\Enums\JednostkaMiary;
use Illuminate\Support\Facades\Log;

class SurowceCalculatorService
{
    /**
     * Fallback ceny surowców jeśli nie ma ustawionej ceny
     */
    private array $cenySurowcow = [
        'kolagen rybi' => 0.050,
        'proszek ananas' => 0.030,
        'vit d' => 0.200,
        'witamina d' => 0.200,
        'kolagen' => 0.050,
        'ananas' => 0.030,
    ];

    /**
     * Oblicza surowce potrzebne do zlecenia
     */
    public function calculateSurowcePotrzebne(Produkt $produkt, int $ilosc): array
    {
        $receptura = $produkt->receptura;
        $opakowanie = $produkt->opakowanie;
        
        // Sprawdź kompatybilność opakowania z recepturą
        $this->validateKompatybilnosc($receptura, $opakowanie);
        
        // Oblicz współczynnik skalowania
        $wspolczynnikSkalowania = $this->calculateWspolczynnikSkalowania($opakowanie);
        
        Log::info('Parametry obliczenia', [
            'typ_receptury' => $receptura->typ_receptury->value ?? 'undefined',
            'jednostka_opakowania' => $opakowanie->jednostka,
            'pojemnosc_opakowania' => $opakowanie->pojemnosc,
            'wspolczynnik_skalowania' => $wspolczynnikSkalowania,
            'ilosc_produktow' => $ilosc
        ]);
        
        $surowcePotrzebne = [];
        
        // Oblicz surowce z receptury
        $surowcePotrzebne = $this->calculateSurowceZReceptury(
            $receptura, 
            $wspolczynnikSkalowania, 
            $ilosc
        );
        
        // Dodaj opakowania do surowców
        if ($opakowanie) {
            $surowcePotrzebne[] = $this->calculateOpakowanie($opakowanie, $ilosc);
        }
        
        Log::info('Obliczone surowce', [
            'count' => count($surowcePotrzebne),
            'surowce' => $surowcePotrzebne
        ]);
        
        return $surowcePotrzebne;
    }

    /**
     * Sprawdza kompatybilność receptury z opakowaniem
     */
    private function validateKompatybilnosc($receptura, $opakowanie): void
    {
        $typReceptury = $receptura->typ_receptury ?? TypReceptury::GRAMY;
        $jednostkaOpakowania = $opakowanie->jednostka instanceof JednostkaOpakowania 
            ? $opakowanie->jednostka->value 
            : $opakowanie->jednostka;
            
        $kompatybilne = ($typReceptury === TypReceptury::GRAMY && $jednostkaOpakowania === 'g') ||
                       ($typReceptury === TypReceptury::MILILITRY && $jednostkaOpakowania === 'ml');
                       
        if (!$kompatybilne) {
            throw new \Exception(
                "Receptura typu '{$typReceptury->value}' nie jest kompatybilna z opakowaniem typu '{$jednostkaOpakowania}'."
            );
        }
    }

    /**
     * Oblicza współczynnik skalowania
     */
    private function calculateWspolczynnikSkalowania($opakowanie): float
    {
        // Receptura zawsze dla 1000 jednostek bazowych (1kg = 1000g lub 1l = 1000ml)
        $pojemnoscBazowa = (float) ($opakowanie->pojemnosc ?? 0); // np. 250g lub 250ml
        return $pojemnoscBazowa / 1000; // 250/1000 = 0,25
    }

    /**
     * Oblicza surowce z receptury
     */
    private function calculateSurowceZReceptury($receptura, float $wspolczynnikSkalowania, int $ilosc): array
    {
        $surowcePotrzebne = [];
        
        foreach ($receptura->surowce as $surowiec) {
            $iloscWRecepturze = (float) ($surowiec->pivot->ilosc ?? 0); // Ilość na 1000 jednostek bazowych (1kg lub 1l)
            
            // Przelicz na jedno opakowanie
            $iloscNaJednoOpakowanie = $iloscWRecepturze * $wspolczynnikSkalowania;
            
            // Przelicz na całe zlecenie
            $iloscNaZlecenie = $iloscNaJednoOpakowanie * $ilosc;
            
            // Pobierz cenę surowca
            $cenaSurowca = $this->getCenaSurowca($surowiec);
            
            // Konwersja enum na string dla jednostki
            $jednostka = $this->getJednostkaString($surowiec->jednostka_miary);
            
            // ZAWSZE używaj jednostek bazowych (g, ml) - bez konwersji na kg/l
            $iloscDoWyswietlenia = $iloscNaZlecenie;
            $jednostkaDoWyswietlenia = $jednostka;
            
            $kostSurowca = $iloscNaZlecenie * $cenaSurowca;
            
            Log::info('Obliczenie surowca', [
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
                'nazwa_naukowa' => '('.$surowiec->nazwa_naukowa.')' ?? '',
                'kod' => $surowiec->kod ?? 'SR-' . $surowiec->id,
                'ilosc' => $iloscDoWyswietlenia,
                'jednostka' => $jednostkaDoWyswietlenia,
                'cena_jednostkowa' => $cenaSurowca,
                'koszt' => $kostSurowca,
            ];
        }
        
        return $surowcePotrzebne;
    }

    /**
     * Oblicza koszty opakowania
     */
    private function calculateOpakowanie($opakowanie, int $ilosc): array
    {
        $cenaOpakowania = (float) ($opakowanie->cena ?? 0);
        
        Log::info('Obliczenie opakowania', [
            'nazwa_opakowania' => $opakowanie->nazwa,
            'ilosc_produktow' => $ilosc,
            'cena_opakowania' => $cenaOpakowania,
            'koszt_calkowity_opakowania' => $ilosc * $cenaOpakowania
        ]);
        
        return [
            'id' => 'opakowanie_' . $opakowanie->id,
            'surowiec_id' => 'opakowanie_' . $opakowanie->id,
            'nazwa' => $opakowanie->nazwa ?? 'Nieznane opakowanie',
            'nazwa_naukowa' => '',
            'kod' => $opakowanie->kod ?? 'OP-' . $opakowanie->id,
            'ilosc' => $ilosc, // Ta wartość to ilość produktów = ilość opakowań
            'jednostka' => 'szt',
            'cena_jednostkowa' => $cenaOpakowania,
            'koszt' => $ilosc * $cenaOpakowania,
        ];
    }

    /**
     * Pobiera cenę surowca (z fallbackiem)
     */
    private function getCenaSurowca($surowiec): float
    {
        $cenaSurowca = (float) ($surowiec->cena_jednostkowa ?? 0);
        
        // Fallback ceny jeśli nie ma ustawionej
        if ($cenaSurowca == 0) {
            $nazwaNormalizowana = strtolower($surowiec->nazwa ?? '');
            foreach ($this->cenySurowcow as $nazwa => $cena) {
                if (str_contains($nazwaNormalizowana, $nazwa)) {
                    $cenaSurowca = (float) $cena;
                    break;
                }
            }
        }
        
        return $cenaSurowca;
    }

    /**
     * Konwertuje enum jednostki na string
     */
    private function getJednostkaString($jednostka): string
    {
        if ($jednostka instanceof JednostkaMiary) {
            return $jednostka->value;
        }
        
        return $jednostka ?? 'g';
    }

    /**
     * Formatuje ilość dla wyświetlenia
     */
    public function formatIlosc(float $ilosc, string $jednostka): string
    {
        // Specjalne formatowanie dla sztuk (opakowania)
        if ($jednostka === 'szt' || $jednostka === 'ml') {
            return number_format($ilosc, 0, ',', ' '); // Format: 1 000 szt
        }
        
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
        
        // Usuń zbędne kropki z końca
        return rtrim($iloscFormatowana, '.');
    }
}