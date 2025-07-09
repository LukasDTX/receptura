<?php

namespace App\Services;

use App\Models\Zlecenie;
use App\Models\Produkt;
use App\Models\Partia;
use App\Models\RuchSurowca;
use App\Enums\TypReceptury;
use App\Enums\JednostkaOpakowania;
use App\Enums\JednostkaMiary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ZlecenieService
{
    public function __construct(
        private MagazynSurowcowService $magazynSurowcowService,
        private SurowceCalculatorService $surowceCalculatorService
    ) {}

    /**
     * Generuje numer zlecenia
     */
    public function generateNumerZlecenia(): string
    {
        $rok = date('Y');
        $miesiac = date('m');
        $ostatnieId = Zlecenie::max('id') ?? 0;
        $noweId = $ostatnieId + 1;
        
        return "ZP/{$rok}/{$miesiac}/{$noweId}";
    }

    /**
     * Przelicza surowce potrzebne do zlecenia
     */
    public function przeliczSurowce(int $produktId, int $ilosc): array
    {
        Log::info('Przelicz surowce - start', [
            'produkt_id' => $produktId,
            'ilosc' => $ilosc
        ]);

        $this->validateSurowceInput($ilosc, $produktId);
        
        $produkt = $this->getProduktWithRelations($produktId);
        $this->validateProduktReceptura($produkt);
        
        return $this->surowceCalculatorService->calculateSurowcePotrzebne($produkt, $ilosc);
    }

    /**
     * Zapisuje przeliczone surowce do istniejącego zlecenia
     */
    public function zapiszSurowceDoZlecenia(Zlecenie $zlecenie, array $surowcePotrzebne): void
    {
        $zlecenie->update([
            'surowce_potrzebne' => $surowcePotrzebne
        ]);
    }

    /**
     * Tworzy nowe zlecenie z przeliczonymi surowcami
     */
    public function createZlecenieWithSurowce(array $data): Zlecenie
    {
        return DB::transaction(function () use ($data) {
            // Pobierz surowce z sesji
            $surowcePotrzebne = session('temp_surowce_potrzebne', []);
            
            // Dodaj surowce do danych zlecenia
            $data['surowce_potrzebne'] = $surowcePotrzebne;
            
            // Utwórz zlecenie
            $zlecenie = Zlecenie::create($data);
            
            // Wyczyść sesję
            session()->forget('temp_surowce_potrzebne');
            
            return $zlecenie;
        });
    }

    /**
     * Sprawdza dostępność surowców w magazynie
     */
    public function sprawdzDostepnoscSurowcow(Zlecenie $zlecenie): array
    {
        if (empty($zlecenie->surowce_potrzebne)) {
            throw new \Exception('Zlecenie nie ma przypisanych surowców potrzebnych.');
        }

        return $this->magazynSurowcowService->pobierzSurowceDoZlecenia($zlecenie);
    }

    /**
     * Pobiera surowce z magazynu do zlecenia
     */
    public function pobierzSurowceDoZlecenia(Zlecenie $zlecenie): array
    {
        if ($zlecenie->status !== 'nowe') {
            throw new \Exception('Można pobierać surowce tylko dla zleceń o statusie "nowe".');
        }

        return DB::transaction(function () use ($zlecenie) {
            $wyniki = $this->magazynSurowcowService->realizujPobraniaDoZlecenia($zlecenie);
            
            // Aktualizuj status zlecenia
            $zlecenie->update(['status' => 'w_realizacji']);
            
            return $wyniki;
        });
    }

    /**
     * Pobiera pobrane surowce dla zlecenia
     */
    public function getPobraneSurowce(Zlecenie $zlecenie): array
    {
        $ruchy = RuchSurowca::where('zlecenie_id', $zlecenie->id)
            ->where('typ_ruchu', 'wydanie_do_produkcji')
            ->with(['partiaSurowca.surowiec'])
            ->orderBy('data_ruchu', 'desc')
            ->get();

        if ($ruchy->isEmpty()) {
            return [];
        }

        return $this->groupSurowceByType($ruchy);
    }

    /**
     * Tworzy partię produktu z zlecenia
     */
    public function utworzPartieZZlecenia(Zlecenie $zlecenie, array $data): Partia
    {
        if ($zlecenie->status !== 'zrealizowane') {
            throw new \Exception('Można tworzyć partie tylko dla zrealizowanych zleceń.');
        }

        return Partia::createFromZlecenie($zlecenie, $data);
    }

    /**
     * Pobiera informacje o produkcie dla formularza
     */
    public function getProduktInfo(int $produktId): array
    {
        $produkt = $this->getProduktWithRelations($produktId);
        
        if (!$produkt) {
            return [];
        }

        return [
            'nazwa' => $produkt->nazwa,
            'kod' => $produkt->kod,
            'receptura' => $produkt->receptura ? [
                'nazwa' => $produkt->receptura->nazwa,
                'koszt_calkowity' => $produkt->receptura->koszt_calkowity ?? 0,
            ] : null,
            'opakowanie' => $produkt->opakowanie ? [
                'nazwa' => $produkt->opakowanie->nazwa,
                'pojemnosc' => $produkt->opakowanie->pojemnosc ?? 0,
                'cena' => $produkt->opakowanie->cena ?? 0,
            ] : null,
        ];
    }

    /**
     * Oblicza koszty zlecenia
     */
    public function obliczKosztyZlecenia(int $produktId, int $ilosc): array
    {
        if ($ilosc <= 0 || !$produktId) {
            return [];
        }

        $produkt = $this->getProduktWithRelations($produktId);
        
        if (!$produkt) {
            return [];
        }

        $kosztRecepturyZaKg = $produkt->receptura ? (float) $produkt->receptura->koszt_calkowity : 0.0;
        $kosztOpakowania = $produkt->opakowanie ? (float) ($produkt->opakowanie->cena ?? 0) : 0.0;
        $pojemnoscOpakowania = $produkt->opakowanie ? (float) $produkt->opakowanie->pojemnosc : 0.0;
        
        $kosztRecepturyNaOpakowanie = 0.0;
        if ($pojemnoscOpakowania > 0) {
            $kosztRecepturyNaOpakowanie = $kosztRecepturyZaKg * ($pojemnoscOpakowania / 1000);
        }
        
        $kosztCalkowity1Sztuki = $kosztRecepturyNaOpakowanie + $kosztOpakowania;
        $kosztCalkowitiegoZlecenia = $kosztCalkowity1Sztuki * $ilosc;
        
        $result = [
            'koszt_receptury_na_opakowanie' => $kosztRecepturyNaOpakowanie,
            'koszt_opakowania' => $kosztOpakowania,
            'koszt_calkowity_1_sztuki' => $kosztCalkowity1Sztuki,
            'koszt_calkowity_zlecenia' => $kosztCalkowitiegoZlecenia,
        ];

        // Oblicz marżę jeśli jest cena sprzedaży
        if ($produkt->cena_sprzedazy > 0) {
            $wartoscSprzedazy = (float) $produkt->cena_sprzedazy * $ilosc;
            $marza = $wartoscSprzedazy - $kosztCalkowitiegoZlecenia;
            $marzaProcent = ($kosztCalkowitiegoZlecenia > 0) ? (($marza / $kosztCalkowitiegoZlecenia) * 100) : 0;
            
            $result['wartosc_sprzedazy'] = $wartoscSprzedazy;
            $result['marza'] = $marza;
            $result['marza_procent'] = $marzaProcent;
        }

        return $result;
    }

    // Private helper methods

    private function validateSurowceInput(int $ilosc, int $produktId): void
    {
        if ($ilosc <= 0) {
            throw new \Exception('Ilość musi być większa od 0.');
        }

        if (!$produktId) {
            throw new \Exception('Wybierz produkt przed przeliczeniem surowców.');
        }
    }

    private function getProduktWithRelations(int $produktId): ?Produkt
    {
        return Produkt::with(['receptura.surowce', 'opakowanie'])->find($produktId);
    }

    private function validateProduktReceptura(Produkt $produkt): void
    {
        if (!$produkt || !$produkt->receptura) {
            throw new \Exception('Produkt nie ma przypisanej receptury.');
        }
    }

    private function groupSurowceByType($ruchy): array
    {
        $surowceGrupowane = $ruchy->groupBy(function ($ruch) {
            return $ruch->partiaSurowca->surowiec->id;
        });

        $podsumowanie = [];
        
        foreach ($surowceGrupowane as $surowiecId => $ruchySurowca) {
            $pierwszyRuch = $ruchySurowca->first();
            $surowiec = $pierwszyRuch->partiaSurowca->surowiec;
            
            $calkowitaPobranaMasa = $ruchySurowca->sum(function ($ruch) {
                return abs($ruch->masa);
            });
            
            $partie = $ruchySurowca->map(function ($ruch) {
                $typMagazynu = $ruch->skad === 'magazyn_produkcji' ? 'Magazyn Produkcji' : 'Magazyn Główny';
                $ikonaMagazynu = $ruch->skad === 'magazyn_produkcji' ? '📦' : '🏪';
                
                return [
                    'numer_partii' => $ruch->partiaSurowca->numer_partii,
                    'masa_pobrana' => abs($ruch->masa),
                    'typ_magazynu' => $typMagazynu,
                    'ikona_magazynu' => $ikonaMagazynu,
                    'data_pobrania' => $ruch->data_ruchu,
                    'lokalizacja_przed' => $ruch->skad === 'magazyn_produkcji' ? 
                        'Mag. Produkcji' : $ruch->partiaSurowca->lokalizacja_magazyn,
                    'uwagi' => $ruch->uwagi,
                ];
            });
            
            $podsumowanie[] = [
                'surowiec' => $surowiec,
                'calkowita_masa' => $calkowitaPobranaMasa,
                'partie' => $partie,
            ];
        }

        return $podsumowanie;
    }
}