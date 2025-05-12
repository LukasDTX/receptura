<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produkt extends Model
{
    use HasFactory;

    protected $table = 'produkt';
    
    protected $fillable = [
        'nazwa',
        'kod',
        'receptura_id',
        'opakowanie_id',
        'opis',
        'koszt_calkowity',
        'cena_sprzedazy',
    ];

    // Dodanie domyślnej wartości dla koszt_calkowity
    protected $attributes = [
        'koszt_calkowity' => 0,
    ];

    public function receptura(): BelongsTo
    {
        return $this->belongsTo(Receptura::class);
    }

    public function opakowanie(): BelongsTo
    {
        return $this->belongsTo(Opakowanie::class);
    }

    public function obliczKosztCalkowity()
    {
        $this->receptura->obliczKosztCalkowity();
        $kosztCalkowity = $this->receptura->koszt_calkowity + $this->opakowanie->cena;
        
        $this->update(['koszt_calkowity' => $kosztCalkowity]);
        
        return $kosztCalkowity;
    }
}