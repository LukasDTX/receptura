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
     * Pobiera nazwę towaru
     */
    public function getNazwaTowaru(): string
    {
        $towar = $this->towar;
        return $towar ? $towar->nazwa : 'Nieznany towar';
    }
    
    /**
     * Sprawdza czy towar jest przeterminowany
     */
    public function isPrzeterminowany(): bool
    {
        return $this->data_waznosci && $this->data_waznosci < now();
    }
    
    /**
     * Sprawdza czy towar wkrótce się przeterminuje (30 dni)
     */
    public function isBliskoPrzeterminowania(): bool
    {
        return $this->data_waznosci && $this->data_waznosci <= now()->addDays(30);
    }
}