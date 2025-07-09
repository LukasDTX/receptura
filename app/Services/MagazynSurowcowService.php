<?php
// app/Services/MagazynSurowcowService.php

namespace App\Services;

use App\Models\PartiaSurowca;
use App\Models\MagazynProdukcji;
use App\Models\Zlecenie;
use App\Models\Surowiec;
use Illuminate\Support\Collection;

class MagazynSurowcowService
{
    /**
     * Pobiera surowce dla zlecenia według logiki FIFO
     */
    public function pobierzSurowceDoZlecenia(Zlecenie $zlecenie): array
    {
        if (empty($zlecenie->surowce_potrzebne)) {
            throw new \InvalidArgumentException('Zlecenie nie ma zdefiniowanych surowców');
        }

        $wyniki = [];
        $braki = [];

        foreach ($zlecenie->surowce_potrzebne as $potrzebnySurowiec) {
            $surowiecId = $potrzebnySurowiec['surowiec_id'] ?? $potrzebnySurowiec['id'];
            
            // Pomiń opakowania
            if (str_starts_with($surowiecId, 'opakowanie_')) {
                continue;
            }

            $potrzebnaMasa = $this->konwertujNaKilogramy(
                $potrzebnySurowiec['ilosc'], 
                $potrzebnySurowiec['jednostka']
            );

            $dostepnaMasa = $this->sprawdzDostepnoscSurowca($surowiecId);

            if ($dostepnaMasa < $potrzebnaMasa) {
                $braki[] = [
                    'surowiec_id' => $surowiecId,
                    'nazwa' => $potrzebnySurowiec['nazwa'],
                    'potrzebna' => $potrzebnaMasa,
                    'dostepna' => $dostepnaMasa,
                    'brak' => $potrzebnaMasa - $dostepnaMasa,
                ];
            } else {
                // Przygotuj plan pobrania
                $planPobrania = $this->zaplanujPobraniaFIFO($surowiecId, $potrzebnaMasa);
                
                $wyniki[] = [
                    'surowiec_id' => $surowiecId,
                    'nazwa' => $potrzebnySurowiec['nazwa'],
                    'potrzebna_masa' => $potrzebnaMasa,
                    'plan_pobrania' => $planPobrania,
                ];
            }
        }

        return [
            'mozliwe_do_realizacji' => empty($braki),
            'braki' => $braki,
            'plan_pobran' => $wyniki,
        ];
    }

    /**
     * Realizuje pobieranie surowców dla zlecenia
     */
    public function realizujPobraniaDoZlecenia(Zlecenie $zlecenie): array
    {
        $analiza = $this->pobierzSurowceDoZlecenia($zlecenie);

        if (!$analiza['mozliwe_do_realizacji']) {
            throw new \Exception(
                'Nie można zrealizować zlecenia z powodu braków: ' . 
                implode(', ', array_column($analiza['braki'], 'nazwa'))
            );
        }

        $wykonanePobrania = [];

        foreach ($analiza['plan_pobran'] as $plan) {
            $surowiecPobrania = [];

            foreach ($plan['plan_pobrania'] as $pobranie) {
                $partia = PartiaSurowca::find($pobranie['partia_id']);
                
                $wydania = $partia->wydajDoZlecenia($pobranie['masa'], $zlecenie);
                
                $surowiecPobrania[] = [
                    'partia_id' => $partia->id,
                    'numer_partii' => $partia->numer_partii,
                    'masa_pobrana' => $pobranie['masa'],
                    'wydania' => $wydania,
                ];
            }

            $wykonanePobrania[] = [
                'surowiec_id' => $plan['surowiec_id'],
                'nazwa' => $plan['nazwa'],
                'calkowita_masa' => $plan['potrzebna_masa'],
                'pobrania' => $surowiecPobrania,
            ];
        }

        return $wykonanePobrania;
    }

    /**
     * Sprawdza dostępność surowca (suma z obu magazynów)
     */
    public function sprawdzDostepnoscSurowca(int $surowiecId): float
    {
        // Dostępność w magazynie głównym
        $masaGlowna = PartiaSurowca::forSurowiec($surowiecId)
            ->dostepne()
            ->sum('masa_pozostala');

        // Dostępność w magazynie produkcji
        $masaProdukcja = MagazynProdukcji::whereHas('partiaSurowca', function($q) use ($surowiecId) {
                $q->where('surowiec_id', $surowiecId);
            })
            ->sum('masa_dostepna');

        return $masaGlowna + $masaProdukcja;
    }

    /**
     * Planuje pobrania według FIFO
     */
    protected function zaplanujPobraniaFIFO(int $surowiecId, float $potrzebnaMasa): array
    {
        $plan = [];
        $pozostalaDoPobrannia = $potrzebnaMasa;

        // 1. Najpierw magazyn produkcji (już otwarte opakowania)
        $pozycjeProdukcji = MagazynProdukcji::whereHas('partiaSurowca', function($q) use ($surowiecId) {
                $q->where('surowiec_id', $surowiecId);
            })
            ->with('partiaSurowca')
            ->where('masa_dostepna', '>', 0)
            ->orderBy('data_przeniesienia', 'asc')
            ->get();

        foreach ($pozycjeProdukcji as $pozycja) {
            if ($pozostalaDoPobrannia <= 0) break;

            $doPobrannia = min($pozostalaDoPobrannia, $pozycja->masa_dostepna);
            
            $plan[] = [
                'typ' => 'magazyn_produkcji',
                'partia_id' => $pozycja->partia_surowca_id,
                'pozycja_id' => $pozycja->id,
                'numer_partii' => $pozycja->partiaSurowca->numer_partii,
                'masa' => $doPobrannia,
                'dostepna_masa' => $pozycja->masa_dostepna,
            ];

            $pozostalaDoPobrannia -= $doPobrannia;
        }

        // 2. Następnie magazyn główny (FIFO po dacie przyjęcia)
        if ($pozostalaDoPobrannia > 0) {
            $partieGlowne = PartiaSurowca::forSurowiec($surowiecId)
                ->dostepne()
                ->get();

            foreach ($partieGlowne as $partia) {
                if ($pozostalaDoPobrannia <= 0) break;

                $doPobrannia = min($pozostalaDoPobrannia, $partia->masa_pozostala);
                
                $plan[] = [
                    'typ' => 'magazyn_glowny',
                    'partia_id' => $partia->id,
                    'numer_partii' => $partia->numer_partii,
                    'masa' => $doPobrannia,
                    'dostepna_masa' => $partia->masa_pozostala,
                    'czy_otworzy_opakowanie' => $doPobrannia < $partia->masa_pozostala,
                ];

                $pozostalaDoPobrannia -= $doPobrannia;
            }
        }

        return $plan;
    }

    /**
     * Konwertuje jednostki na kilogramy
     */
    protected function konwertujNaKilogramy(float $ilosc, string $jednostka): float
    {
        return match(strtolower($jednostka)) {
            'g' => $ilosc / 1000,
            'kg' => $ilosc,
            'ml' => $ilosc / 1000, // Zakładając gęstość ~1g/ml
            'l' => $ilosc,
            default => $ilosc / 1000, // Domyślnie traktuj jak gramy
        };
    }

    /**
     * Raport stanu magazynu
     */
    public function generujRaportStanu(): array
    {
        $partie = PartiaSurowca::with('surowiec')
            ->whereIn('status', ['nowa', 'otwarta'])
            ->where('masa_pozostala', '>', 0)
            ->get()
            ->groupBy('surowiec_id');

        $raport = [];

        foreach ($partie as $surowiecId => $partieGrupy) {
            $surowiec = $partieGrupy->first()->surowiec;
            $calkowitaMasa = $partieGrupy->sum('masa_pozostala');
            
            // Dodaj masę z magazynu produkcji
            $masaProdukcja = MagazynProdukcji::whereHas('partiaSurowca', function($q) use ($surowiecId) {
                    $q->where('surowiec_id', $surowiecId);
                })
                ->sum('masa_dostepna');

            $raport[] = [
                'surowiec_id' => $surowiecId,
                'nazwa' => $surowiec->nazwa,
                'kod' => $surowiec->kod,
                'masa_magazyn_glowny' => $calkowitaMasa,
                'masa_magazyn_produkcji' => $masaProdukcja,
                'masa_calkowita' => $calkowitaMasa + $masaProdukcja,
                'liczba_parti' => $partieGrupy->count(),
                'partie_szczegoly' => $partieGrupy->map(fn($p) => [
                    'numer_partii' => $p->numer_partii,
                    'masa_pozostala' => $p->masa_pozostala,
                    'data_przyjecia' => $p->data_przyjecia,
                    'data_waznosci' => $p->data_waznosci,
                    'status' => $p->status,
                ]),
            ];
        }

        return $raport;
    }

    /**
     * Znajduje partie wkrótce przeterminowane
     */
    public function znajdzPartieWkrotcePrzeterminowane(int $dni = 30): Collection
    {
        return PartiaSurowca::with('surowiec')
            ->whereIn('status', ['nowa', 'otwarta'])
            ->where('masa_pozostala', '>', 0)
            ->whereBetween('data_waznosci', [now(), now()->addDays($dni)])
            ->orderBy('data_waznosci', 'asc')
            ->get();
    }
}