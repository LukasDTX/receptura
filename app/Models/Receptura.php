<?php

namespace App\Models;

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
        'opis',
        'koszt_calkowity',
        'meta',
    ];
    
    // Dodajemy atrybut cast, aby pole meta było automatycznie konwertowane z JSON
    protected $casts = [
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
        $koszt = 0;
        $sumaProcentowa = 0;

        foreach ($this->surowce as $surowiec) {
            $iloscWGramach = 0;
            $ilosc = $surowiec->pivot->ilosc;
            
            // Przeliczenie kosztu i ilości w gramach w zależności od jednostki miary surowca
            if ($surowiec->jednostka_miary === 'g') {
                // Dla gramów: ilość w gramach * cena jednostkowa za gram
                $koszt += $surowiec->cena_jednostkowa * $ilosc;
                
                // Dla sumy procentowej: ilość w gramach / 1000 * 100%
                $iloscWGramach = $ilosc;
            } elseif ($surowiec->jednostka_miary === 'kg') {
                // Dla kilogramów: ilość w kg * cena jednostkowa za kg
                $koszt += $surowiec->cena_jednostkowa * $ilosc;
                
                // Dla sumy procentowej: ilość w kg * 1000 / 1000 * 100%
                $iloscWGramach = $ilosc * 1000;
            } elseif ($surowiec->jednostka_miary === 'ml') {
                // Dla mililitrów: zakładamy, że 1ml = 1g dla uproszczenia
                $koszt += $surowiec->cena_jednostkowa * $ilosc;
                
                // Dla sumy procentowej: ilość w ml / 1000 * 100%
                $iloscWGramach = $ilosc;
            } elseif ($surowiec->jednostka_miary === 'l') {
                // Dla litrów: zakładamy, że 1l = 1kg dla uproszczenia
                $koszt += $surowiec->cena_jednostkowa * $ilosc;
                
                // Dla sumy procentowej: ilość w l * 1000 / 1000 * 100%
                $iloscWGramach = $ilosc * 1000;
            } else {
                // Dla innych jednostek (np. sztuk)
                $koszt += $surowiec->cena_jednostkowa * $ilosc;
                // Nie dodajemy do sumy procentowej, bo nie da się przeliczyć sztuk na gramy
            }
            
            // Obliczenie procentu (ilość w gramach / 1000g * 100%)
            $procent = ($iloscWGramach / 1000) * 100;
            $sumaProcentowa += $procent;
        }

        // Zaokrąglenie do dwóch miejsc po przecinku, aby uniknąć błędów zaokrąglenia
        $sumaProcentowa = round($sumaProcentowa, 2);
        
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
    }
    
    // Pomocnicza metoda do pobierania sumy procentowej
    public function getSumaProcentowa()
    {
        $meta = is_array($this->meta) ? $this->meta : (json_decode($this->meta, true) ?: []);
        return $meta['suma_procentowa'] ?? 0;
    }
}