<?php

namespace App\Models;

use App\Enums\StatusDostawy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dostawa extends Model
{
    use HasFactory;

    protected $table = 'dostawy';
    
    protected $fillable = [
        'numer_dostawy',
        'dostawca',
        'data_zamowienia',
        'planowana_data_dostawy',
        'rzeczywista_data_dostawy',
        'status',
        'wartosc_calkowita',
        'uwagi',
    ];
    
    protected $casts = [
        'data_zamowienia' => 'date',
        'planowana_data_dostawy' => 'date',
        'rzeczywista_data_dostawy' => 'date',
        'status' => StatusDostawy::class,
    ];

    public function pozycje(): HasMany
    {
        return $this->hasMany(DostawawPozycja::class);
    }
    
    /**
     * Generuje numer dostawy
     */
    public static function generateNumerDostawy(): string
    {
        $rok = date('Y');
        $miesiac = date('m');
        
        $ostatniNumer = static::where('numer_dostawy', 'LIKE', "DOS/{$rok}/{$miesiac}/%")
            ->count();
            
        $nowyNumer = $ostatniNumer + 1;
        
        return "DOS/{$rok}/{$miesiac}/" . str_pad($nowyNumer, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * Oblicza wartość całkowitą dostawy
     */
    public function obliczWartoscCalkowita(): void
    {
        $wartosc = $this->pozycje()->sum('wartosc');
        $this->update(['wartosc_calkowita' => $wartosc]);
    }
    
    /**
     * Przyjmuje dostawę do magazynu
     */
    public function przyjmijDoMagazynu(): void
    {
        foreach ($this->pozycje as $pozycja) {
            // Utwórz ruch magazynowy
            RuchMagazynowy::create([
                'numer_dokumentu' => $this->numer_dostawy,
                'typ_ruchu' => \App\Enums\TypRuchuMagazynowego::PRZYJECIE,
                'typ_towaru' => 'surowiec',
                'towar_id' => $pozycja->surowiec_id,
                'numer_partii' => $pozycja->numer_partii_dostawcy,
                'ilosc' => $pozycja->ilosc_dostarczona,
                'jednostka' => $pozycja->jednostka,
                'cena_jednostkowa' => $pozycja->cena_jednostkowa,
                'wartosc' => $pozycja->wartosc,
                'data_ruchu' => $this->rzeczywista_data_dostawy ?? now(),
                'zrodlo_docelowe' => 'Dostawa od: ' . $this->dostawca,
                'uwagi' => 'Przyjęcie dostawy: ' . $this->numer_dostawy,
                'user_id' => auth()->id(),
            ]);
            
            // Zaktualizuj stan magazynu
            StanMagazynu::updateOrCreate(
                [
                    'typ_towaru' => 'surowiec',
                    'towar_id' => $pozycja->surowiec_id,
                    'numer_partii' => $pozycja->numer_partii_dostawcy,
                ],
                [
                    'ilosc_dostepna' => 0,
                    'jednostka' => $pozycja->jednostka,
                    'wartosc' => 0,
                    'data_waznosci' => $pozycja->data_waznosci,
                ]
            );
            
            $stanMagazynu = StanMagazynu::where([
                'typ_towaru' => 'surowiec',
                'towar_id' => $pozycja->surowiec_id,
                'numer_partii' => $pozycja->numer_partii_dostawcy,
            ])->first();
            
            if ($stanMagazynu) {
                $stanMagazynu->increment('ilosc_dostepna', $pozycja->ilosc_dostarczona);
                $stanMagazynu->increment('wartosc', $pozycja->wartosc);
            }
        }
        
        $this->update(['status' => StatusDostawy::DOSTARCZONA]);
    }
}