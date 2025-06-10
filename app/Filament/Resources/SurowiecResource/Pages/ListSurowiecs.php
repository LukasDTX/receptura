<?php

namespace App\Filament\Resources\SurowiecResource\Pages;

use App\Filament\Resources\SurowiecResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use App\Models\Surowiec;

class ListSurowiecs extends ListRecords
{
    protected static string $resource = SurowiecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj surowiec')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->size('lg'),
                
            // AKCJA IMPORTU Z NADPISYWANIEM
            Actions\Action::make('import_surowce')
                ->label('Importuj z Excel')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Plik Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->visibility('private'),
                        
                    Forms\Components\Toggle::make('update_existing')
                        ->label('Nadpisuj istniejące surowce')
                        ->helperText('Jeśli kod surowca już istnieje w bazie, dane zostaną zaktualizowane')
                        ->default(true),
                        
                    Forms\Components\Textarea::make('instrukcje')
                        ->label('Instrukcje')
                        ->default('Format pliku Excel:
Kolumna A: Nazwa surowca (wymagane)
Kolumna B: Nazwa naukowa (opcjonalne)
Kolumna C: Kod surowca (wymagane - unikalny identyfikator)
Kolumna D: Opis (opcjonalne)
Kolumna E: Cena jednostkowa (liczba)
Kolumna F: Jednostka miary (g lub ml)
Kolumna G: Kategoria (opcjonalne - np. OE, S, E, P, O, M, D, K, W, MIN, A)

Pierwszy wiersz to nagłówki - zostanie pominięty.

WAŻNE: Kod surowca jest kluczem - jeśli już istnieje:
• Z zaznaczoną opcją "Nadpisuj" - dane zostaną zaktualizowane
• Bez zaznaczonej opcji - surowiec zostanie pominięty')
                        ->disabled()
                        ->rows(10)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    try {
                        $filePath = storage_path('app/private/' . $data['file']);
                        
                        if (!file_exists($filePath)) {
                            throw new \Exception('Plik nie został znaleziony.');
                        }

                        // Sprawdź czy PhpSpreadsheet jest dostępne
                        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
                            throw new \Exception('PhpSpreadsheet nie jest zainstalowane. Uruchom: composer require phpoffice/phpspreadsheet');
                        }

                        // Wczytaj plik Excel
                        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                        $reader->setReadDataOnly(true);
                        $spreadsheet = $reader->load($filePath);
                        $worksheet = $spreadsheet->getActiveSheet();
                        
                        $rows = $worksheet->toArray();
                        
                        if (count($rows) < 2) {
                            throw new \Exception('Plik musi zawierać co najmniej 2 wiersze (nagłówki + dane).');
                        }
                        
                        $updateExisting = $data['update_existing'] ?? true;
                        
                        $created = 0;
                        $updated = 0;
                        $skipped = 0;
                        $errors = [];
                        
                        // Pomijamy pierwszy wiersz (nagłówki) - zaczynamy od indeksu 1
                        for ($i = 1; $i < count($rows); $i++) {
                            $row = $rows[$i];
                            
                            // Sprawdź czy wiersz nie jest pusty
                            if (empty(trim($row[0] ?? '')) && empty(trim($row[2] ?? ''))) {
                                continue;
                            }
                            
                            try {
                                $nazwa = trim($row[0] ?? '');
                                $nazwaNaukowa = trim($row[1] ?? '');
                                $kod = trim($row[2] ?? '');
                                $opis = trim($row[3] ?? '');
                                $cenaJednostkowa = floatval($row[4] ?? 0);
                                $jednostkaMiary = strtolower(trim($row[5] ?? 'g'));
                                $kategoria = strtoupper(trim($row[6] ?? ''));
                                
                                // Walidacja podstawowych pól
                                if (empty($nazwa)) {
                                    $errors[] = "Wiersz " . ($i + 1) . ": Brak nazwy surowca";
                                    continue;
                                }
                                
                                if (empty($kod)) {
                                    $errors[] = "Wiersz " . ($i + 1) . ": Brak kodu surowca (wymagany do identyfikacji)";
                                    continue;
                                }
                                
                                // Walidacja długości
                                if (strlen($nazwa) > 255) {
                                    $errors[] = "Wiersz " . ($i + 1) . ": Nazwa zbyt długa (max 255 znaków)";
                                    continue;
                                }
                                
                                if (strlen($kod) > 255) {
                                    $errors[] = "Wiersz " . ($i + 1) . ": Kod zbyt długi (max 255 znaków)";
                                    continue;
                                }
                                
                                // Walidacja jednostki miary
                                if (!in_array($jednostkaMiary, ['g', 'ml'])) {
                                    $jednostkaMiary = 'g'; // Domyślna wartość
                                }
                                //walidacja kategorii
                                if (!empty($kategoria)) {
    $kategoria = \App\Enums\KategoriaSurowca::tryFrom($kategoria);
}
                                // Przygotuj dane do zapisu
                                $surowiecData = [
                                    'nazwa' => $nazwa,
                                    'nazwa_naukowa' => !empty($nazwaNaukowa) ? substr($nazwaNaukowa, 0, 255) : null,
                                    'kod' => $kod,
                                    'opis' => !empty($opis) ? $opis : null,
                                    'cena_jednostkowa' => $cenaJednostkowa,
                                    'jednostka_miary' => $jednostkaMiary,
                                    'kategoria' => $kategoria
                                ];
                                
                                // Sprawdź czy surowiec już istnieje
                                $existingSurowiec = Surowiec::where('kod', $kod)->first();
                                
                                if ($existingSurowiec) {
                                    if ($updateExisting) {
                                        // Zaktualizuj istniejący surowiec
                                        $existingSurowiec->update($surowiecData);
                                        $updated++;
                                    } else {
                                        // Pomiń istniejący surowiec
                                        $skipped++;
                                        $errors[] = "Wiersz " . ($i + 1) . ": Surowiec o kodzie '{$kod}' już istnieje - pominięto";
                                    }
                                } else {
                                    // Utwórz nowy surowiec
                                    Surowiec::create($surowiecData);
                                    $created++;
                                }
                                
                            } catch (\Exception $e) {
                                $errors[] = "Wiersz " . ($i + 1) . ": " . $e->getMessage();
                            }
                        }
                        
                        // Usuń tymczasowy plik
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        
                        // Przygotuj szczegółowy raport
                        $total = $created + $updated;
                        
                        if ($total > 0 || $skipped > 0) {
                            $message = "📊 PODSUMOWANIE IMPORTU:\n\n";
                            
                            if ($created > 0) {
                                $message .= "✅ Utworzono nowych surowców: {$created}\n";
                            }
                            
                            if ($updated > 0) {
                                $message .= "🔄 Zaktualizowano istniejących: {$updated}\n";
                            }
                            
                            if ($skipped > 0) {
                                $message .= "⏭️ Pominięto (już istnieją): {$skipped}\n";
                            }
                            
                            $message .= "\n📈 Łącznie przetworzono: " . ($total + $skipped);
                            
                            if (!empty($errors)) {
                                $errorCount = count($errors);
                                $message .= "\n\n⚠️ Błędów: {$errorCount}";
                                $message .= "\n" . implode("\n", array_slice($errors, 0, 5));
                                if ($errorCount > 5) {
                                    $message .= "\n... i " . ($errorCount - 5) . " więcej błędów.";
                                }
                            }
                            
                            $notificationType = 'success';
                            if ($total == 0 && $skipped > 0) {
                                $notificationType = 'warning';
                            } elseif (!empty($errors) && $total == 0) {
                                $notificationType = 'danger';
                            }
                            
                            Notification::make()
                                ->title('Import zakończony')
                                ->body($message)
                                ->{$notificationType}()
                                ->duration(15000)
                                ->send();
                        } else {
                            throw new \Exception('Nie przetworzono żadnych surowców. Sprawdź format pliku i dane.');
                        }
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Błąd importu')
                            ->body('Wystąpił błąd podczas importu: ' . $e->getMessage())
                            ->danger()
                            ->duration(10000)
                            ->send();
                    }
                })
                ->modalHeading('Importuj surowce z pliku Excel')
                ->modalSubmitActionLabel('Importuj')
                ->modalCancelActionLabel('Anuluj')
                ->modalWidth('2xl'),
                
            // POBIERZ SZABLON EXCEL
            Actions\Action::make('pobierz_szablon')
                ->label('Pobierz szablon Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    try {
                        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                            throw new \Exception('PhpSpreadsheet nie jest zainstalowane.');
                        }
                        
                        // Utwórz nowy arkusz
                        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                        $sheet = $spreadsheet->getActiveSheet();
                        
                        // Ustaw nagłówki
                        $headers = [
                            'A1' => 'Nazwa surowca',
                            'B1' => 'Nazwa naukowa',
                            'C1' => 'Kod surowca',
                            'D1' => 'Opis',
                            'E1' => 'Cena jednostkowa',
                            'F1' => 'Jednostka miary',
                        ];
                        
                        foreach ($headers as $cell => $value) {
                            $sheet->setCellValue($cell, $value);
                            $sheet->getStyle($cell)->getFont()->setBold(true);
                            $sheet->getStyle($cell)->getFill()
                                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('E3F2FD');
                        }
                        
                        // Dodaj przykładowe dane
                        $exampleData = [
                            ['Kolagen rybi', 'Collagen piscis', 'KOL-RYB-001', 'Kolagen z ryb morskich', 0.05, 'g'],
                            ['Witamina D3', 'Cholecalciferol', 'VIT-D3-001', 'Witamina D3 naturalna', 0.20, 'g'],
                            ['Olej z pestek winogron', 'Vitis vinifera seed oil', 'OIL-WIN-001', 'Olej zimnotłoczony', 0.03, 'ml'],
                            ['Proszek malinowy', 'Rubus idaeus', 'PRO-MAL-001', 'Naturalny proszek z malin', 0.08, 'g'],
                            ['Ekstrakt z zielonej herbaty', 'Camellia sinensis extract', 'EXT-TEA-001', 'Standaryzowany ekstrakt', 0.15, 'g'],
                        ];
                        
                        $row = 2;
                        foreach ($exampleData as $data) {
                            $col = 'A';
                            foreach ($data as $value) {
                                $sheet->setCellValue($col . $row, $value);
                                $col++;
                            }
                            $row++;
                        }
                        
                        // Dostosuj szerokość kolumn
                        foreach (range('A', 'F') as $column) {
                            $sheet->getColumnDimension($column)->setAutoSize(true);
                        }
                        
                        // Dodaj komentarze/walidację
                        $sheet->getComment('C1')->getText()->createTextRun('WYMAGANE: Unikalny kod identyfikujący surowiec');
                        $sheet->getComment('F1')->getText()->createTextRun('Dozwolone: g, ml');
                        
                        // Dodaj informacje na dole arkusza
                        $infoRow = $row + 2;
                        $sheet->setCellValue('A' . $infoRow, 'INSTRUKCJE:');
                        $sheet->getStyle('A' . $infoRow)->getFont()->setBold(true);
                        
                        $instructions = [
                            'A. Kolumna "Kod surowca" jest kluczowa - musi być unikalna',
                            'B. Jeśli kod już istnieje w bazie, surowiec zostanie zaktualizowany',
                            'C. Jeśli kod nie istnieje, zostanie utworzony nowy surowiec',
                            'D. Jednostka miary: tylko "g" (gramy) lub "ml" (mililitry)',
                            'E. Pierwszy wiersz z nagłówkami zostanie pominięty podczas importu',
                        ];
                        
                        foreach ($instructions as $index => $instruction) {
                            $sheet->setCellValue('A' . ($infoRow + 1 + $index), $instruction);
                            $sheet->getStyle('A' . ($infoRow + 1 + $index))->getFont()->setSize(9);
                        }
                        
                        // Zapisz plik
                        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                        $fileName = 'szablon_surowce_' . date('Y-m-d_H-i') . '.xlsx';
                        $filePath = storage_path('app/private/' . $fileName);
                        
                        $writer->save($filePath);
                        
                        // Zwróć odpowiedź do pobrania
                        return response()->download($filePath, $fileName)->deleteFileAfterSend();
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Błąd generowania szablonu')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}