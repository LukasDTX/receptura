<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zlecenie extends Model
{
    use HasFactory;

    protected $table = 'zlecenie';
    
    protected $fillable = [
        'numer',
        'produkt_id',
        'ilosc',
        'data_zlecenia',
        'planowana_data_realizacji',
        'status',
        'surowce_potrzebne',
        'uwagi',
    ];
    
    protected $casts = [
        'data_zlecenia' => 'date',
        'planowana_data_realizacji' => 'date',
        'surowce_potrzebne' => 'array',
    ];
    
    public function produkt(): BelongsTo
    {
        return $this->belongsTo(Produkt::class);
    }
    
    /**
     * Oblicza ilość potrzebnych surowców na podstawie produktu i ilości
     * 
     * @return array
     */
    public function obliczPotrzebneSurowce(): array
    {
        try {
            $produkt = $this->produkt;
            $ilosc = $this->ilosc;
            
            if (!$produkt || $ilosc <= 0) {
                return [];
            }
            
            $receptura = $produkt->receptura;
            $opakowanie = $produkt->opakowanie;
            
            if (!$receptura || !$opakowanie) {
                return [];
            }
            
            // Standardowa receptura jest dla 1kg produktu
            // Obliczamy współczynnik skalowania na podstawie pojemności opakowania
            $wspSkalowania = $opakowanie->pojemnosc / 1000; // pojemnosc w gramach / 1000g (1kg)
            
            // Pobieramy wszystkie surowce z receptury
            $surowce = $receptura->surowce;
            
            $wynik = [];
            
            foreach ($surowce as $surowiec) {
                $iloscPodstawowa = $surowiec->pivot->ilosc; // ilość z receptury dla 1kg
                $iloscPotrzebna = $iloscPodstawowa * $wspSkalowania * $ilosc;
                
                // Konwersja enum na string
                $jednostka = $surowiec->jednostka_miary;
                if ($jednostka instanceof \App\Enums\JednostkaMiary) {
                    $jednostka = $jednostka->value;
                } else {
                    $jednostka = $jednostka ?? 'g';
                }
                
                // Dla kilogramów i litrów, zamieniamy na większą jednostkę, jeśli ilość przekracza 1000
                if (($jednostka === 'g' && $iloscPotrzebna >= 1000) || 
                    ($jednostka === 'ml' && $iloscPotrzebna >= 1000)) {
                    if ($jednostka === 'g') {
                        $iloscPotrzebna = $iloscPotrzebna / 1000;
                        $jednostka = 'kg';
                    } else {
                        $iloscPotrzebna = $iloscPotrzebna / 1000;
                        $jednostka = 'l';
                    }
                }
                
                $wynik[] = [
                    'surowiec_id' => $surowiec->id,
                    'nazwa' => $surowiec->nazwa,
                    'kod' => $surowiec->kod,
                    'ilosc' => $iloscPotrzebna,
                    'jednostka' => $jednostka,
                    'cena_jednostkowa' => $surowiec->cena_jednostkowa,
                    'koszt' => $surowiec->cena_jednostkowa * $iloscPotrzebna,
                ];
            }
            
            // Zapisz wyniki w polu surowce_potrzebne
            $this->surowce_potrzebne = $wynik;
            $this->save();
            
            return $wynik;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Błąd podczas obliczania potrzebnych surowców: ' . $e->getMessage(), [
                'zlecenie_id' => $this->id,
                'produkt_id' => $this->produkt_id ?? null,
                'ilosc' => $this->ilosc ?? null,
                'exception' => $e,
            ]);
            
            return [];
        }
    }
    
    /**
     * Wersja metody obliczPotrzebneSurowce, która nie zapisuje do bazy danych
     * Używana do podglądu przed zapisem
     * 
     * @return array
     */
    public function obliczPotrzebneSurowcePreview(): array
    {
        try {
            $produkt = $this->produkt;
            $ilosc = $this->ilosc;
            
            if (!$produkt || $ilosc <= 0) {
                return [];
            }
            
            $receptura = $produkt->receptura;
            $opakowanie = $produkt->opakowanie;
            
            if (!$receptura || !$opakowanie) {
                return [];
            }
            
            // Standardowa receptura jest dla 1kg produktu
            // Obliczamy współczynnik skalowania na podstawie pojemności opakowania
            $wspSkalowania = $opakowanie->pojemnosc / 1000; // pojemnosc w gramach / 1000g (1kg)
            
            // Pobieramy wszystkie surowce z receptury
            $surowce = $receptura->surowce;
            
            $wynik = [];
            
            foreach ($surowce as $surowiec) {
                $iloscPodstawowa = $surowiec->pivot->ilosc; // ilość z receptury dla 1kg
                $iloscPotrzebna = $iloscPodstawowa * $wspSkalowania * $ilosc;
                
                // Konwersja enum na string
                $jednostka = $surowiec->jednostka_miary;
                if ($jednostka instanceof \App\Enums\JednostkaMiary) {
                    $jednostka = $jednostka->value;
                } else {
                    $jednostka = $jednostka ?? 'g';
                }
                
                // Dla kilogramów i litrów, zamieniamy na większą jednostkę, jeśli ilość przekracza 1000
                if (($jednostka === 'g' && $iloscPotrzebna >= 1000) || 
                    ($jednostka === 'ml' && $iloscPotrzebna >= 1000)) {
                    if ($jednostka === 'g') {
                        $iloscPotrzebna = $iloscPotrzebna / 1000;
                        $jednostka = 'kg';
                    } else {
                        $iloscPotrzebna = $iloscPotrzebna / 1000;
                        $jednostka = 'l';
                    }
                }
                
                $wynik[] = [
                    'surowiec_id' => $surowiec->id,
                    'nazwa' => $surowiec->nazwa,
                    'kod' => $surowiec->kod,
                    'ilosc' => $iloscPotrzebna,
                    'jednostka' => $jednostka,
                    'cena_jednostkowa' => $surowiec->cena_jednostkowa,
                    'koszt' => $surowiec->cena_jednostkowa * $iloscPotrzebna,
                ];
            }
            
            return $wynik;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Błąd podczas obliczania potrzebnych surowców (podgląd): ' . $e->getMessage(), [
                'zlecenie_id' => $this->id,
                'produkt_id' => $this->produkt_id ?? null,
                'ilosc' => $this->ilosc ?? null,
                'exception' => $e,
            ]);
            
            throw $e; // Przekaż wyjątek dalej, aby mógł być obsłużony przez interfejs
        }
    }
}