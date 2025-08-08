<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StanMagazynu extends Model
{
    use HasFactory;

    protected $table = 'magazyn';
    
    protected $fillable = [
        'typ_towaru',
        'towar_id',
        'numer_partii',
        'ilosc_dostepna',
        'jednostka',
        'wartosc',
        'data_waznosci',
        'lokalizacja',
    ];
    
    protected $casts = [
        'data_waznosci' => 'date',
    ];

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
     * Pobiera nazwę towaru na podstawie typu i ID
     */
    public function getNazwaTowaru(): string
    {
        if ($this->typ_towaru === 'surowiec') {
            return $this->surowiec?->nazwa ?? 'Nieznany surowiec';
        } elseif ($this->typ_towaru === 'produkt') {
            return $this->produkt?->nazwa ?? 'Nieznany produkt';
        }
        
        return 'Nieznany towar';
    }

    /**
     * Relacja do surowca
     */
    public function surowiec()
    {
        return $this->belongsTo(\App\Models\Surowiec::class, 'towar_id');
    }

    /**
     * Relacja do produktu  
     */
    public function produkt()
    {
        return $this->belongsTo(\App\Models\Produkt::class, 'towar_id');
    }

    /**
     * Sprawdza czy pozycja jest przeterminowana
     */
    public function isPrzeterminowany(): bool
    {
        return $this->data_waznosci && $this->data_waznosci < now();
    }

    /**
     * Sprawdza czy pozycja jest blisko przeterminowania (30 dni)
     */
    public function isBliskoPrzeterminowania(): bool
    {
        return $this->data_waznosci && $this->data_waznosci <= now()->addDays(30) && $this->data_waznosci >= now();
    }
    
    /**
     * Sprawdza czy towar jest przeterminowany
     */
    // public function isPrzeterminowany(): bool
    // {
    //     return $this->data_waznosci && $this->data_waznosci < now();
    // }
    
    // /**
    //  * Sprawdza czy towar wkrótce się przeterminuje (30 dni)
    //  */
    // public function isBliskoPrzeterminowania(): bool
    // {
    //     return $this->data_waznosci && $this->data_waznosci <= now()->addDays(30);
    // }
}