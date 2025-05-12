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

        foreach ($this->surowce as $surowiec) {
            // Przeliczenie kosztu w zależności od jednostki miary surowca
            if ($surowiec->jednostka_miary === 'g') {
                // Dla gramów: ilość w gramach * cena jednostkowa za gram
                // Ale receptura jest dla 1kg, więc potrzebujemy 1000g
                // Przykład: receptura wymaga 200g surowca, cena to 0.05 PLN/g
                // Koszt: 200g * 0.05 PLN/g = 10 PLN
                $koszt += $surowiec->cena_jednostkowa * $surowiec->pivot->ilosc;
            } elseif ($surowiec->jednostka_miary === 'kg') {
                // Dla kilogramów: ilość w kg * cena jednostkowa za kg
                // Receptura jest dla 1kg, więc jeśli mamy 0.2kg surowca, to 0.2 * cena za kg
                $koszt += $surowiec->cena_jednostkowa * $surowiec->pivot->ilosc;
            } elseif ($surowiec->jednostka_miary === 'ml') {
                // Dla mililitrów: ilość w ml * cena jednostkowa za ml
                $koszt += $surowiec->cena_jednostkowa * $surowiec->pivot->ilosc;
            } elseif ($surowiec->jednostka_miary === 'l') {
                // Dla litrów: ilość w l * cena jednostkowa za l
                $koszt += $surowiec->cena_jednostkowa * $surowiec->pivot->ilosc;
            } else {
                // Dla innych jednostek (np. sztuk)
                $koszt += $surowiec->cena_jednostkowa * $surowiec->pivot->ilosc;
            }
        }

        $this->update(['koszt_calkowity' => $koszt]);
        
        return $koszt;
    }
}
