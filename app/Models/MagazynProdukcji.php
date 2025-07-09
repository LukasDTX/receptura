<?php
// app/Models/MagazynProdukcji.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MagazynProdukcji extends Model
{
    use HasFactory;

    protected $table = 'magazyn_produkcji';
    
    protected $fillable = [
        'partia_surowca_id',
        'masa_dostepna',
        'lokalizacja',
        'data_przeniesienia',
    ];
    
    protected $casts = [
        'data_przeniesienia' => 'date',
    ];

    public function partiaSurowca(): BelongsTo
    {
        return $this->belongsTo(PartiaSurowca::class, 'partia_surowca_id');
    }

    /**
     * Pobiera surowiec przez relację
     */
    public function surowiec()
    {
        return $this->hasOneThrough(
            Surowiec::class,
            PartiaSurowca::class,
            'id', // klucz obcy w PartiaSurowca
            'id', // klucz obcy w Surowiec
            'partia_surowca_id', // klucz lokalny w MagazynProdukcji
            'surowiec_id' // klucz lokalny w PartiaSurowca
        );
    }

    /**
     * Scope dla dostępnych pozycji
     */
    public function scopeDostepne($query)
    {
        return $query->where('masa_dostepna', '>', 0);
    }

    /**
     * Scope FIFO - najstarsze jako pierwsze
     */
    public function scopeFifo($query)
    {
        return $query->orderBy('data_przeniesienia', 'asc');
    }
}