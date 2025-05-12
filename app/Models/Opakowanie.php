<?php

namespace App\Models;

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
        'cena',
    ];

    public function produkty(): HasMany
    {
        return $this->hasMany(Produkt::class);
    }
}
