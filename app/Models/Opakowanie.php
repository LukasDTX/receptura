<?php

namespace App\Models;

use App\Enums\JednostkaOpakowania;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opakowanie extends Model
{
    use HasFactory;

    protected $table = 'opakowanie';
    
    protected $fillable = [
        'nazwa',
        'kod',
        'opis',
        'pojemnosc',
        'jednostka',
        'cena',
    ];
    
    protected $casts = [
        'jednostka' => JednostkaOpakowania::class,
    ];

    public function produkty(): HasMany
    {
        return $this->hasMany(Produkt::class);
    }
    
    /**
     * Zwraca pojemność z jednostką jako string
     */
    public function getPojemnoscFormatowana(): string
    {
        $jednostka = $this->jednostka instanceof JednostkaOpakowania 
            ? $this->jednostka->value 
            : $this->jednostka;
            
        return number_format($this->pojemnosc, $this->pojemnosc == intval($this->pojemnosc) ? 0 : 2) . ' ' . $jednostka;
    }
    
    /**
     * Zwraca pojemność w jednostkach bazowych (gramy lub mililitry)
     * Używane do obliczeń w recepturach
     */
    public function getPojemnoscWJednostkachBazowych(): float
    {
        // Pojemność jest już przechowywana w jednostkach bazowych (g lub ml)
        return (float) $this->pojemnosc;
    }
    
    /**
     * Sprawdza kompatybilność z typem receptury
     */
    public function isKompatybilnyZReceptura(\App\Enums\TypReceptury $typReceptury): bool
    {
        return ($this->jednostka === JednostkaOpakowania::GRAMY && $typReceptury === \App\Enums\TypReceptury::GRAMY) ||
               ($this->jednostka === JednostkaOpakowania::MILILITRY && $typReceptury === \App\Enums\TypReceptury::MILILITRY);
    }
}
