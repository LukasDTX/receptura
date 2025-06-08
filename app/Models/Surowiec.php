<?php

namespace App\Models;

use App\Enums\JednostkaMiary; // Dodaj tę linię, jeśli używasz PHP enum
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Surowiec extends Model
{
    use HasFactory;

    protected $table = 'surowiec';

    protected $fillable = [
        'nazwa',
        'nazwa_naukowa',
        'kod',
        'opis',
        'cena_jednostkowa',
        'jednostka_miary',
    ];

    protected $casts = [
        'jednostka_miary' => JednostkaMiary::class, // Jeśli używasz PHP enum (PHP 8.1+)
        'cena_jednostkowa' => 'decimal:2',
    ];

    public function receptury(): BelongsToMany
    {
        return $this->belongsToMany(Receptura::class, 'receptura_surowiec')
                    ->withPivot('ilosc')
                    ->withTimestamps();
    }

    // Alias dla zgodności z konwencją Filament
    public function recepturas(): BelongsToMany
    {
        return $this->receptury();
    }
}
