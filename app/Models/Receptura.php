<?php

namespace App\Models;

use App\Enums\TypReceptury;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receptura extends Model
{
    use HasFactory;

    protected $table = 'receptura';
    
    protected $fillable = [
        'nazwa',
        'kod',
        'typ_receptury',
        'opis',
        'koszt_calkowity',
        'meta',
    ];
    
    protected $casts = [
        'typ_receptury' => TypReceptury::class,
        'meta' => 'array',
    ];

    public function surowce(): BelongsToMany
    {
        return $this->belongsToMany(Surowiec::class, 'receptura_surowiec')
                    ->withPivot('ilosc')
                    ->withTimestamps();
    }

    public function produkty(): HasMany
    {
        return $this->hasMany(Produkt::class);
    }

    public function obliczKosztCalkowity()
    {
        try {
            $koszt = 0;
            $sumaProcentowa = 0;
            
            // Debug log
            \Illuminate\Support\Facades\Log::info('Obliczanie kosztu dla receptury: ' . $this->id, [
                'surowce_count' => $this->surowce->count(),
                'typ_receptury' => $this->typ_receptury?->value
            ]);

            foreach ($this->surowce as $surowiec) {
                $iloscWBazowejJednostce = 0;
                $ilosc = (float) ($surowiec->pivot->ilosc ?? 0);
                
                // Pobierz jednostkę miary
                $jednostkaMiary = $surowiec->jednostka_miary;
                if ($jednostkaMiary instanceof \App\Enums\JednostkaMiary) {
                    $jednostkaMiary = $jednostkaMiary->value;
                }
                
                // Debug dla każdego surowca
                \Illuminate\Support\Facades\Log::info('Przetwarzanie surowca', [
                    'nazwa' => $surowiec->nazwa,
                    'ilosc' => $ilosc,
                    'jednostka' => $jednostkaMiary,
                    'cena' => $surowiec->cena_jednostkowa
                ]);
                
                // Przeliczenie kosztu i ilości w zależności od jednostki miary surowca
                if ($jednostkaMiary === 'g') {
                    $koszt += $surowiec->cena_jednostkowa * $ilosc;
                    $iloscWBazowejJednostce = $ilosc;
                } elseif ($jednostkaMiary === 'kg') {
                    $koszt += $surowiec->cena_jednostkowa * $ilosc;
                    $iloscWBazowejJednostce = $ilosc * 1000;
                } elseif ($jednostkaMiary === 'ml') {
                    $koszt += $surowiec->cena_jednostkowa * $ilosc;
                    $iloscWBazowejJednostce = $ilosc;
                } elseif ($jednostkaMiary === 'l') {
                    $koszt += $surowiec->cena_jednostkowa * $ilosc;
                    $iloscWBazowejJednostce = $ilosc * 1000;
                } else {
                    // Dla innych jednostek (np. sztuk)
                    $koszt += $surowiec->cena_jednostkowa * $ilosc;
                    // Nie dodajemy do sumy procentowej, bo nie da się przeliczyć sztuk na gramy/ml
                }
                
                // Obliczenie procentu - zawsze dla 1000 jednostek bazowych (1kg = 1000g lub 1l = 1000ml)
                if ($iloscWBazowejJednostce > 0) {
                    $procent = ($iloscWBazowejJednostce / 1000) * 100;
                    $sumaProcentowa += $procent;
                    
                    \Illuminate\Support\Facades\Log::info('Obliczony procent', [
                        'surowiec' => $surowiec->nazwa,
                        'ilosc_bazowa' => $iloscWBazowejJednostce,
                        'procent' => $procent,
                        'suma_dotychczas' => $sumaProcentowa
                    ]);
                }
            }

            // Zaokrąglenie do dwóch miejsc po przecinku
            $sumaProcentowa = round($sumaProcentowa, 2);
            
            // Debug końcowy
            \Illuminate\Support\Facades\Log::info('Wynik obliczeń', [
                'koszt_calkowity' => $koszt,
                'suma_procentowa' => $sumaProcentowa
            ]);
            
            // Pobierz aktualne metadane
            $metaData = [];
            if ($this->meta !== null) {
                if (is_array($this->meta)) {
                    $metaData = $this->meta;
                } elseif (is_string($this->meta)) {
                    $metaData = json_decode($this->meta, true) ?: [];
                }
            }
            
            // Zaktualizuj sumę procentową
            $metaData['suma_procentowa'] = $sumaProcentowa;
            
            // Zapisz zaktualizowane metadane i koszt całkowity
            $this->koszt_calkowity = $koszt;
            $this->meta = $metaData;
            $this->save();
            
            // Odśwież model, aby mieć aktualne dane
            $this->refresh();
            
            return $koszt;
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Błąd podczas obliczania kosztu receptury: ' . $e->getMessage(), [
                'receptura_id' => $this->id,
                'exception' => $e,
            ]);
            
            throw $e;
        }
    }
    
    public function getSumaProcentowa()
    {
        $meta = is_array($this->meta) ? $this->meta : (json_decode($this->meta, true) ?: []);
        return $meta['suma_procentowa'] ?? 0;
    }
    
    /**
     * Zwraca etykietę jednostki dla tego typu receptury
     */
    public function getJednostkaLabel(): string
    {
        return $this->typ_receptury === TypReceptury::GRAMY ? '1kg' : '1l';
    }
    
    /**
     * Zwraca surowce zgodne z typem receptury
     */
    public function getKompatybilneSurowce()
    {
        $query = \App\Models\Surowiec::query();
        
        if ($this->typ_receptury === TypReceptury::GRAMY) {
            // Dla receptur w gramach - pokaż surowce w g, kg
            $query->whereIn('jednostka_miary', ['g', 'kg']);
        } elseif ($this->typ_receptury === TypReceptury::MILILITRY) {
            // Dla receptur w mililitrach - pokaż surowce w ml, l
            $query->whereIn('jednostka_miary', ['ml', 'l']);
        }
        
        return $query->get();
    }
}