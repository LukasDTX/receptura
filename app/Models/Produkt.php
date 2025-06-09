<?php

namespace App\Models;

use App\Enums\OkresWaznosci;
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
        'okres_waznosci',
        'meta',
    ];
    
    // Dodajemy atrybut cast, aby pole meta było automatycznie konwertowane z JSON
    protected $casts = [
        'meta' => 'array',
        'okres_waznosci' => OkresWaznosci::class,
    ];

    // Dodanie domyślnej wartości dla koszt_calkowity
    protected $attributes = [
        'koszt_calkowity' => 0,
        'okres_waznosci' => '12M',
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
    
    /**
     * Oblicza datę ważności na podstawie daty produkcji
     */
    public function obliczDataWaznosci(\Carbon\Carbon $dataProdukcji): \Carbon\Carbon
    {
        $miesiace = $this->okres_waznosci->getMonths();
        return $dataProdukcji->copy()->addMonths($miesiace);
    }
}