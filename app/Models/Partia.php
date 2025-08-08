<?php

namespace App\Models;

use App\Enums\StatusPartii;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partia extends Model
{
    use HasFactory;

    protected $table = 'partie';
    
    protected $fillable = [
        'numer_partii',
        'zlecenie_id',
        'produkt_id',
        'ilosc_wyprodukowana',
        'data_produkcji',
        'data_waznosci',
        'status',
        'surowce_uzyte',
        'koszt_produkcji',
        'uwagi',
    ];

    protected $casts = [
        'data_produkcji' => 'date',
        'data_waznosci' => 'date',
        'status' => StatusPartii::class,
        'surowce_uzyte' => 'array',
    ];

    public function zlecenie(): BelongsTo
    {
        return $this->belongsTo(Zlecenie::class);
    }

    public function produkt(): BelongsTo
    {
        return $this->belongsTo(Produkt::class);
    }
    
    /**
     * Generuje numer partii
     */

// public static function generateNumerPartii(\DateTimeInterface $dataProdukcji = null, $excludeId = null): string
// {
//     // Strefa czasowa Twoja, np. Warszawa
//     $localTz = new \DateTimeZone('Europe/Warsaw');

//     // Jeśli brak daty, ustaw na dziś lokalnie z godziną 00:00:00
//     $localDate = $dataProdukcji 
//         ? $dataProdukcji->setTimezone($localTz)->setTime(0, 0, 0)
//         : (new \DateTimeImmutable('now', $localTz))->setTime(0, 0, 0);

//     // Format daty do numeru partii
//     $rok = $localDate->format('Y');
//     $miesiac = $localDate->format('m');
//     $dzien = $localDate->format('d');

//     // Teraz musimy policzyć ile jest już partii na ten dzień.
//     // WAŻNE: gdziekolwiek w bazie jest data_produkcji, musimy ją porównać w UTC z zakresem odpowiadającym Twojemu lokalnemu dniu.
//     // Dlatego wyliczamy zakres UTC odpowiadający temu lokalnemu dniu:

//     // Początek lokalnego dnia w UTC
//     $startUtc = $localDate->setTimezone(new \DateTimeZone('UTC'));

//     // Koniec lokalnego dnia w UTC (23:59:59)
//     $endLocalDate = $localDate->modify('+1 day')->modify('-1 second');
//     $endUtc = $endLocalDate->setTimezone(new \DateTimeZone('UTC'));

//     // Liczymy ile partii jest już w tym zakresie dat produkcji
//     // Wykluczamy aktualnie przetwarzany rekord (jeśli podano ID)
//     $query = static::whereBetween('data_produkcji', [$startUtc, $endUtc]);
    
//     if ($excludeId !== null) {
//         $query->where('id', '!=', $excludeId);
//     }
    
//     $ostatniNumer = $query->count();

//     $numerDnia = str_pad($ostatniNumer + 1, 3, '0', STR_PAD_LEFT);

//     return "P{$rok}{$miesiac}{$dzien}-{$numerDnia}";
// }



    /**
     * Tworzy partię z zlecenia
    //  */
    // public static function createFromZlecenie(Zlecenie $zlecenie, array $data = []): self
    // {
    //     $defaultData = [
    //         'numer_partii' => static::generateNumerPartii(),
    //         'zlecenie_id' => $zlecenie->id,
    //         'produkt_id' => $zlecenie->produkt_id,
    //         'ilosc_wyprodukowana' => $zlecenie->ilosc,
    //         'data_produkcji' => now()->toDateString(),
    //         'data_waznosci' => now()->addMonths(12)->toDateString(), // Domyślnie rok ważności
    //         'status' => StatusPartii::WYPRODUKOWANA,
    //         'surowce_uzyte' => $zlecenie->surowce_potrzebne,
    //         'koszt_produkcji' => 0, // Będzie obliczony
    //     ];
        
    //     $partia = static::create(array_merge($defaultData, $data));
        
    //     // Oblicz koszt produkcji
    //     $partia->obliczKosztProdukcji();
        
    //     // Utwórz ruchy magazynowe
    //     $partia->utworzRuchyMagazynowe();
        
    //     return $partia;
    // }
    
    /**
     * Oblicza koszt produkcji na podstawie użytych surowców
     */
    public function obliczKosztProdukcji(): void
    {
        $koszt = 0;
        
        if (!empty($this->surowce_uzyte)) {
            foreach ($this->surowce_uzyte as $surowiec) {
                $koszt += $surowiec['koszt'] ?? 0;
            }
        }
        
        $this->update(['koszt_produkcji' => $koszt]);
    }
    
    /**
     * Tworzy ruchy magazynowe dla partii
     */
    public function utworzRuchyMagazynowe(): void
    {
        // Przyjęcie produktu do magazynu
        RuchMagazynowy::create([
            'typ_ruchu' => \App\Enums\TypRuchuMagazynowego::PRODUKCJA,
            'typ_towaru' => 'produkt',
            'towar_id' => $this->produkt_id,
            'numer_partii' => $this->numer_partii,
            'ilosc' => $this->ilosc_wyprodukowana,
            'jednostka' => 'szt',
            'cena_jednostkowa' => $this->koszt_produkcji / $this->ilosc_wyprodukowana,
            'wartosc' => $this->koszt_produkcji,
            'data_ruchu' => $this->data_produkcji,
            'zrodlo_docelowe' => 'Produkcja - Zlecenie: ' . $this->zlecenie->numer,
            'uwagi' => 'Automatyczne przyjęcie z produkcji',
        ]);
        
        // Wydanie surowców z magazynu
        if (!empty($this->surowce_uzyte)) {
            foreach ($this->surowce_uzyte as $surowiec) {
                if (isset($surowiec['surowiec_id']) && str_starts_with($surowiec['surowiec_id'], 'opakowanie_')) {
                    continue; // Pomiń opakowania - będą obsłużone osobno
                }
                
                RuchMagazynowy::create([
                    'typ_ruchu' => \App\Enums\TypRuchuMagazynowego::PRODUKCJA,
                    'typ_towaru' => 'surowiec',
                    'towar_id' => $surowiec['surowiec_id'] ?? $surowiec['id'],
                    'numer_partii' => null, // Można rozszerzyć o śledzenie partii surowców
                    'ilosc' => -($surowiec['ilosc'] ?? 0), // Ujemna ilość = wydanie
                    'jednostka' => $surowiec['jednostka'] ?? 'g',
                    'cena_jednostkowa' => $surowiec['cena_jednostkowa'] ?? 0,
                    'wartosc' => -($surowiec['koszt'] ?? 0),
                    'data_ruchu' => $this->data_produkcji,
                    'zrodlo_docelowe' => 'Produkcja - Partia: ' . $this->numer_partii,
                    'uwagi' => 'Automatyczne wydanie do produkcji',
                ]);
            }
        }
        
        // Zaktualizuj stan magazynu
        $this->aktualizujStanMagazynu();
    }
    
    /**
     * Aktualizuje stan magazynu
     */
    public function aktualizujStanMagazynu(): void
    {
        // Dodaj produkt do magazynu
        StanMagazynu::updateOrCreate(
            [
                'typ_towaru' => 'produkt',
                'towar_id' => $this->produkt_id,
                'numer_partii' => $this->numer_partii,
            ],
            [
                'ilosc_dostepna' => $this->ilosc_wyprodukowana,
                'jednostka' => 'szt',
                'wartosc' => $this->koszt_produkcji,
                'data_waznosci' => $this->data_waznosci,
            ]
        );
        
        $this->update(['status' => StatusPartii::W_MAGAZYNIE]);
    }
}