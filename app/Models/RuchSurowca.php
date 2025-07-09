<?php
// app/Models/RuchSurowca.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuchSurowca extends Model
{
    use HasFactory;

    protected $table = 'ruchy_surowcow';
    
    protected $fillable = [
        'numer_dokumentu',
        'typ_ruchu',
        'partia_surowca_id',
        'zlecenie_id',
        'masa',
        'masa_przed',
        'masa_po',
        'skad',
        'dokad',
        'data_ruchu',
        'uwagi',
        'user_id',
    ];
    
    protected $casts = [
        'data_ruchu' => 'date',
    ];

    public function partiaSurowca(): BelongsTo
    {
        return $this->belongsTo(PartiaSurowca::class, 'partia_surowca_id');
    }

    public function zlecenie(): BelongsTo
    {
        return $this->belongsTo(Zlecenie::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope dla konkretnej partii
     */
    public function scopeForPartia($query, int $partiaId)
    {
        return $query->where('partia_surowca_id', $partiaId);
    }

    /**
     * Scope dla typu ruchu
     */
    public function scopeOfType($query, string $typ)
    {
        return $query->where('typ_ruchu', $typ);
    }
}

