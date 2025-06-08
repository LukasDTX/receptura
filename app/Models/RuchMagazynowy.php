<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RuchMagazynowy extends Model
{
    use HasFactory;

    protected $table = 'ruchy_magazynowe';
    
    protected $fillable = [
        'numer_dokumentu',
        'typ_ruchu',
        'typ_towaru',
        'towar_id',
        'numer_partii',
        'ilosc',
        'jednostka',
        'cena_jednostkowa',
        'wartosc',
        'data_ruchu',
        'zrodlo_docelowe',
        'uwagi',
        'user_id',
    ];
    
    protected $casts = [
        'typ_ruchu' => \App\Enums\TypRuchuMagazynowego::class,
        'data_ruchu' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Pobiera towar (surowiec lub produkt)
     */
    public function towar()
    {
        if ($this->typ_towaru === 'surowiec') {
            return $this->belongsTo(Surowiec::class, 'towar_id');
        } else {
            return $this->belongsTo(Produkt::class, 'towar_id');
        }
    }
    
    /**
     * Pobiera nazwę towaru
     */
    public function getNazwaTowaru(): string
    {
        $towar = $this->towar;
        return $towar ? $towar->nazwa : 'Nieznany towar';
    }
    
    /**
     * Tworzy ruch magazynowy i aktualizuje stan
     */
    public static function createRuch(array $data): self
    {
        $ruch = static::create($data);
        $ruch->aktualizujStanMagazynu();
        return $ruch;
    }
    
    /**
     * Aktualizuje stan magazynu na podstawie ruchu
     */
    public function aktualizujStanMagazynu(): void
    {
        $stanMagazynu = StanMagazynu::firstOrCreate(
            [
                'typ_towaru' => $this->typ_towaru,
                'towar_id' => $this->towar_id,
                'numer_partii' => $this->numer_partii,
            ],
            [
                'ilosc_dostepna' => 0,
                'jednostka' => $this->jednostka,
                'wartosc' => 0,
            ]
        );
        
        // Aktualizuj ilość
        if (in_array($this->typ_ruchu, [\App\Enums\TypRuchuMagazynowego::PRZYJECIE, \App\Enums\TypRuchuMagazynowego::KOREKTA_PLUS, \App\Enums\TypRuchuMagazynowego::PRODUKCJA])) {
            $stanMagazynu->ilosc_dostepna += abs($this->ilosc);
            $stanMagazynu->wartosc += abs($this->wartosc);
        } else {
            $stanMagazynu->ilosc_dostepna -= abs($this->ilosc);
            $stanMagazynu->wartosc -= abs($this->wartosc);
        }
        
        // Usuń rekord jeśli ilość = 0
        if ($stanMagazynu->ilosc_dostepna <= 0) {
            $stanMagazynu->delete();
        } else {
            $stanMagazynu->save();
        }
    }
}