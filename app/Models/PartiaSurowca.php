<?php
// app/Models/PartiaSurowca.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartiaSurowca extends Model
{
    use HasFactory;

    protected $table = 'partie_surowcow';
    
    protected $fillable = [
        'numer_partii',
        'surowiec_id',
        'numer_partii_dostawcy',
        'masa_brutto',
        'masa_netto',
        'masa_pozostala',
        'typ_opakowania',
        'cena_za_kg',
        'data_przyjecia',
        'data_waznosci',
        'data_otwarcia',
        'status',
        'lokalizacja_magazyn',
        'uwagi',
    ];
    
    protected $casts = [
        'data_przyjecia' => 'date',
        'data_waznosci' => 'date',
        'data_otwarcia' => 'date',
    ];

    public function surowiec(): BelongsTo
    {
        return $this->belongsTo(Surowiec::class);
    }

    public function ruchy(): HasMany
    {
        return $this->hasMany(RuchSurowca::class, 'partia_surowca_id');
    }

    public function magazynProdukcji(): HasMany
    {
        return $this->hasMany(MagazynProdukcji::class, 'partia_surowca_id');
    }

    /**
     * Generuje unikalny numer partii surowca
     */
    public static function generateNumerPartii(): string
    {
        $rok = date('Y');
        $miesiac = date('m');
        $dzien = date('d');
        
        // Znajdź ostatni numer w dniu
        $ostatniNumer = static::whereDate('data_przyjecia', today())
            ->count();
            
        $numerDnia = str_pad($ostatniNumer + 1, 3, '0', STR_PAD_LEFT);
        
        return "PS{$rok}{$miesiac}{$dzien}-{$numerDnia}";
    }

    /**
     * Sprawdza czy partia jest dostępna do użycia
     */
    public function isDostepna(): bool
    {
        return in_array($this->status, ['nowa', 'otwarta']) && 
               $this->masa_pozostala > 0 &&
               (!$this->data_waznosci || $this->data_waznosci >= now());
    }

    /**
     * Sprawdza czy partia jest przeterminowana
     */
    public function isPrzeterminowana(): bool
    {
        return $this->data_waznosci && $this->data_waznosci < now();
    }

    /**
     * Pobiera dostępną masę (w magazynie głównym + magazynie produkcji)
     */
    public function getMasaDostepnaCalkowita(): float
    {
        $masaGlowna = $this->masa_pozostala;
        $masaProdukcja = $this->magazynProdukcji()->sum('masa_dostepna');
        
        return $masaGlowna + $masaProdukcja;
    }

    /**
     * Wydaje określoną masę surowca do zlecenia
     */
    public function wydajDoZlecenia(float $masa, Zlecenie $zlecenie): array
    {
        if ($masa <= 0) {
            throw new \InvalidArgumentException('Masa musi być większa od 0');
        }

        if ($masa > $this->getMasaDostepnaCalkowita()) {
            throw new \InvalidArgumentException('Niewystarczająca ilość surowca w partii');
        }

        $wydania = [];
        $pozostalaDoWydania = $masa;

        // 1. Najpierw wydaj z magazynu produkcji (FIFO)
        $pozycjeProdukcji = $this->magazynProdukcji()
            ->where('masa_dostepna', '>', 0)
            ->orderBy('data_przeniesienia', 'asc')
            ->get();

        foreach ($pozycjeProdukcji as $pozycja) {
            if ($pozostalaDoWydania <= 0) break;

            $doWydata = min($pozostalaDoWydania, $pozycja->masa_dostepna);
            
            // Utwórz ruch
            RuchSurowca::create([
                'typ_ruchu' => 'wydanie_do_produkcji',
                'partia_surowca_id' => $this->id,
                'zlecenie_id' => $zlecenie->id,
                'masa' => -$doWydata,
                'masa_przed' => $pozycja->masa_dostepna,
                'masa_po' => $pozycja->masa_dostepna - $doWydata,
                'skad' => 'magazyn_produkcji',
                'dokad' => 'zlecenie_' . $zlecenie->numer,
                'data_ruchu' => now(),
                'uwagi' => "Wydanie do zlecenia {$zlecenie->numer}",
                'user_id' => auth()->id(),
            ]);

            // Aktualizuj pozycję w magazynie produkcji
            $pozycja->masa_dostepna -= $doWydata;
            if ($pozycja->masa_dostepna <= 0) {
                $pozycja->delete();
            } else {
                $pozycja->save();
            }

            $wydania[] = [
                'zrodlo' => 'magazyn_produkcji',
                'masa' => $doWydata,
                'pozycja_id' => $pozycja->id,
            ];

            $pozostalaDoWydania -= $doWydata;
        }

        // 2. Reszta z magazynu głównego
        if ($pozostalaDoWydania > 0) {
            if ($pozostalaDoWydania > $this->masa_pozostala) {
                throw new \InvalidArgumentException('Niewystarczająca ilość w magazynie głównym');
            }

            $masaPrzed = $this->masa_pozostala;
            $masaPo = $this->masa_pozostala - $pozostalaDoWydania;

            // Jeśli wydajemy całe opakowanie
            if ($masaPo == 0) {
                $this->status = 'zuzyta';
            } else {
                // Jeśli częściowo - przenieś resztę do magazynu produkcji
                if ($this->status == 'nowa') {
                    $this->status = 'otwarta';
                    $this->data_otwarcia = now();
                }

                MagazynProdukcji::create([
                    'partia_surowca_id' => $this->id,
                    'masa_dostepna' => $masaPo,
                    'data_przeniesienia' => now(),
                    'lokalizacja' => 'PROD-' . $this->surowiec->kod,
                ]);

                $this->masa_pozostala = 0; // Całe opakowanie zostało "otwarte"
            }

            // Utwórz ruch
            RuchSurowca::create([
                'typ_ruchu' => 'wydanie_do_produkcji',
                'partia_surowca_id' => $this->id,
                'zlecenie_id' => $zlecenie->id,
                'masa' => -$pozostalaDoWydania,
                'masa_przed' => $masaPrzed,
                'masa_po' => 0, // Bo reszta przeszła do magazynu produkcji
                'skad' => 'magazyn_glowny',
                'dokad' => 'zlecenie_' . $zlecenie->numer,
                'data_ruchu' => now(),
                'uwagi' => "Wydanie do zlecenia {$zlecenie->numer}" . ($masaPo > 0 ? ", reszta {$masaPo}kg -> magazyn produkcji" : ""),
                'user_id' => auth()->id(),
            ]);

            $this->save();

            $wydania[] = [
                'zrodlo' => 'magazyn_glowny',
                'masa' => $pozostalaDoWydania,
                'masa_do_produkcji' => $masaPo > 0 ? $masaPo : 0,
            ];
        }

        return $wydania;
    }

    /**
     * Przyjmuje partię do magazynu
     */
    public static function przyjmijPartie(array $dane): self
    {
        $partia = static::create(array_merge($dane, [
            'numer_partii' => static::generateNumerPartii(),
            'masa_pozostala' => $dane['masa_netto'],
            'status' => 'nowa',
            'data_przyjecia' => now(),
        ]));

        // Utwórz ruch przyjęcia
        RuchSurowca::create([
            'typ_ruchu' => 'przyjecie',
            'partia_surowca_id' => $partia->id,
            'masa' => $partia->masa_netto,
            'masa_przed' => 0,
            'masa_po' => $partia->masa_netto,
            'skad' => 'dostawca',
            'dokad' => 'magazyn_glowny',
            'data_ruchu' => now(),
            'uwagi' => 'Przyjęcie nowej partii od dostawcy',
            'user_id' => auth()->id(),
        ]);

        return $partia;
    }

    /**
     * Scope dla partii dostępnych (FIFO)
     */
    public function scopeDostepne($query)
    {
        return $query->whereIn('status', ['nowa', 'otwarta'])
                    ->where('masa_pozostala', '>', 0)
                    ->where(function($q) {
                        $q->whereNull('data_waznosci')
                          ->orWhere('data_waznosci', '>=', now());
                    })
                    ->orderBy('data_przyjecia', 'asc')
                    ->orderBy('data_otwarcia', 'asc');
    }

    /**
     * Scope dla konkretnego surowca
     */
    public function scopeForSurowiec($query, int $surowiecId)
    {
        return $query->where('surowiec_id', $surowiecId);
    }
}