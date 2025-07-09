<?php

namespace App\Services;

use App\Models\Produkt;
use Illuminate\Support\HtmlString;

class ZlecenieFormService
{
    public function __construct(
        private ZlecenieService $zlecenieService,
        private SurowceCalculatorService $calculatorService
    ) {}

    /**
     * Generuje zawartość wskaźnika statusu
     */
    public function generateStatusIndicator($record, $produktId, $ilosc, $surowcePreeliczone): HtmlString
    {
        if ($record) {
            return new HtmlString('');
        }
        
        $tempSurowce = session('temp_surowce_potrzebne');
        
        if (!$produktId) {
            return $this->createStatusBox(
                'ℹ️ Wybierz produkt aby rozpocząć',
                '#f9fafb',
                '#d1d5db',
                '#374151'
            );
        }
        
        if (!$ilosc || $ilosc <= 0) {
            return $this->createStatusBox(
                'ℹ️ Ustaw ilość produktów',
                '#f9fafb',
                '#d1d5db',
                '#374151'
            );
        }
        
        if (($surowcePreeliczone === true || $surowcePreeliczone === 'true') && !empty($tempSurowce)) {
            return $this->createStatusBox(
                '✅ Gotowe do zapisu',
                '#d1fae5',
                '#10b981',
                '#065f46'
            );
        }
        
        return $this->createStatusBox(
            '⏳ Kliknij przycisk kalkulatora przy polu "Ilość" aby przeliczyć surowce',
            '#fef3c7',
            '#f59e0b',
            '#92400e'
        );
    }

    /**
     * Generuje zawartość sekcji surowców
     */
    public function generateSurowceContent($record): string
    {
        $surowce = $this->getSurowceData($record);
        
        if (!$surowce || empty($surowce)) {
            return '<div style="padding: 20px; text-align: center; color: #6b7280;">
                    <p>Lista surowców zostanie wygenerowana po przeliczeniu.</p>
                    <p><small>Wybierz produkt, ustaw ilość i kliknij przycisk kalkulatora przy polu "Ilość".</small></p>
                    </div>';
        }
        
        return $this->generateSurowceTable($surowce);
    }

    /**
     * Sprawdza czy sekcja surowców powinna być widoczna
     */
    public function isSurowceSectionVisible($record, $surowcePreeliczone): bool
    {
        if ($record) {
            return true;
        }
        
        $tempSurowce = session('temp_surowce_potrzebne');
        
        return ($surowcePreeliczone === true || $surowcePreeliczone === 'true') && 
               !empty($tempSurowce) && 
               is_array($tempSurowce) && 
               count($tempSurowce) > 0;
    }

    /**
     * Sprawdza czy sekcja surowców powinna być zwinięta
     */
    public function isSurowceSectionCollapsed($record, $surowcePreeliczone): bool
    {
        if (!$record) {
            $tempSurowce = session('temp_surowce_potrzebne');
            return !($surowcePreeliczone && !empty($tempSurowce));
        }
        return false;
    }

    /**
     * Generuje informacje o produkcie
     */
    public function generateProduktInfo($produktId): string
    {
        if (!$produktId) {
            return 'Wybierz produkt, aby zobaczyć szczegóły.';
        }
        
        $produktInfo = $this->zlecenieService->getProduktInfo($produktId);
        
        if (empty($produktInfo)) {
            return 'Nie znaleziono produktu.';
        }
        
        return $this->formatProduktInfo($produktInfo);
    }

    /**
     * Generuje informacje o kosztach
     */
    public function generateKosztyInfo($produktId, $ilosc): string
    {
        if ($ilosc === null || $ilosc === '' || !is_numeric($ilosc)) {
            $ilosc = 0;
        } else {
            $ilosc = (int) $ilosc;
        }
        
        if (!$produktId) {
            return 'Wybierz produkt, aby zobaczyć obliczenia.';
        }
        
        if ($ilosc <= 0) {
            return 'Ustaw ilość większą od 0, aby zobaczyć obliczenia.';
        }
        
        $koszty = $this->zlecenieService->obliczKosztyZlecenia($produktId, $ilosc);
        
        return $this->formatKosztyInfo($koszty, $ilosc);
    }

    /**
     * Sprawdza czy sekcja informacji o produkcie powinna być widoczna
     */
    public function isProduktInfoSectionVisible($record, $produktId): bool
    {
        if ($record) {
            return true;
        }
        
        return !empty($produktId);
    }

    // Private helper methods

    private function createStatusBox(string $message, string $bgColor, string $borderColor, string $textColor): HtmlString
    {
        return new HtmlString(
            "<div style=\"padding: 8px 12px; background-color: {$bgColor}; border: 1px solid {$borderColor}; border-radius: 6px; color: {$textColor};\">
                {$message}
            </div>"
        );
    }

    private function getSurowceData($record): ?array
    {
        if (!$record) {
            return session('temp_surowce_potrzebne');
        }
        
        $freshRecord = \App\Models\Zlecenie::find($record->id);
        return $freshRecord ? $freshRecord->surowce_potrzebne : $record->surowce_potrzebne;
    }

    private function generateSurowceTable(array $surowce): string
    {
        $html = '<div style="overflow-x: auto;">';
        $html .= '<table class="w-full text-left border-collapse border border-gray-300">';
        $html .= $this->generateTableHeader();
        $html .= '<tbody>';
        
        $suma = 0;
        foreach ($surowce as $surowiec) {
            $html .= $this->generateTableRow($surowiec);
            $suma += $surowiec['koszt'];
        }
        
        $html .= '</tbody>';
        $html .= $this->generateTableFooter($suma);
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }

    private function generateTableHeader(): string
    {
        return '<thead>
            <tr style="background-color: #f9fafb;">
                <th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Nazwa</th>
                <th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Kod</th>
                <th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Ilość</th>
                <th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Cena jedn. (PLN/g)</th>
                <th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Koszt</th>
            </tr>
        </thead>';
    }

    private function generateTableRow(array $surowiec): string
    {
        $ilosc = (float) $surowiec['ilosc'];
        $jednostka = $surowiec['jednostka'] ?? '';
        $iloscFormatowana = $this->calculatorService->formatIlosc($ilosc, $jednostka);
        
        return '<tr style="border-bottom: 1px solid #e5e7eb;">
            <td class="py-2 px-4">' . htmlspecialchars($surowiec['nazwa']) . htmlspecialchars($surowiec['nazwa_naukowa'] ?? '') . '</td>
            <td class="py-2 px-4">' . htmlspecialchars($surowiec['kod']) . '</td>
            <td class="py-2 px-4">' . $iloscFormatowana . ' ' . htmlspecialchars($jednostka) . '</td>
            <td class="py-2 px-4">' . number_format($surowiec['cena_jednostkowa'], 2) . ' PLN</td>
            <td class="py-2 px-4 font-semibold">' . number_format($surowiec['koszt'], 2) . ' PLN</td>
        </tr>';
    }

    private function generateTableFooter(float $suma): string
    {
        return '<tfoot>
            <tr style="background-color: #f3f4f6; font-weight: bold;">
                <td colspan="4" class="py-3 px-4 text-right">SUMA KOSZTÓW:</td>
                <td class="py-3 px-4 text-lg">' . number_format($suma, 2) . ' PLN</td>
            </tr>
        </tfoot>';
    }

    private function formatProduktInfo(array $produktInfo): string
    {
        $info = "<strong>Nazwa:</strong> {$produktInfo['nazwa']}<br>";
        $info .= "<strong>Kod:</strong> {$produktInfo['kod']}<br>";
        
        if ($produktInfo['receptura']) {
            $info .= "<strong>Receptura:</strong> {$produktInfo['receptura']['nazwa']}<br>";
            $info .= "<strong>Koszt receptury za kg:</strong> " . number_format($produktInfo['receptura']['koszt_calkowity'], 2) . " PLN/kg<br>";
        }
        
        if ($produktInfo['opakowanie']) {
            $info .= "<strong>Opakowanie:</strong> {$produktInfo['opakowanie']['nazwa']}<br>";
            $info .= "<strong>Pojemność:</strong> " . number_format($produktInfo['opakowanie']['pojemnosc'], 0) . " g<br>";
            $info .= "<strong>Koszt opakowania:</strong> " . number_format($produktInfo['opakowanie']['cena'], 2) . " PLN<br>";
        }
        
        return $info;
    }

    private function formatKosztyInfo(array $koszty, int $ilosc): string
    {
        if (empty($koszty)) {
            return 'Nie można obliczyć kosztów.';
        }
        
        $info = "<strong>Na 1 sztukę:</strong><br>";
        $info .= "Koszt receptury: " . number_format($koszty['koszt_receptury_na_opakowanie'], 2) . " PLN<br>";
        $info .= "Koszt opakowania: " . number_format($koszty['koszt_opakowania'], 2) . " PLN<br>";
        $info .= "<strong>Koszt całkowity:</strong> " . number_format($koszty['koszt_calkowity_1_sztuki'], 2) . " PLN<br>";
        
        $info .= "<hr style='margin: 8px 0; border: 1px solid #e5e7eb;'>";
        $info .= "<strong>Na {$ilosc} szt.:</strong><br>";
        $info .= "Koszt całkowity zlecenia: " . number_format($koszty['koszt_calkowity_zlecenia'], 2) . " PLN<br>";
        
        if (isset($koszty['wartosc_sprzedazy'])) {
            $info .= "Wartość sprzedaży: " . number_format($koszty['wartosc_sprzedazy'], 2) . " PLN<br>";
            $info .= "Marża: " . number_format($koszty['marza'], 2) . " PLN (" . number_format($koszty['marza_procent'], 2) . "%)";
        }
        
        return $info;
    }
}