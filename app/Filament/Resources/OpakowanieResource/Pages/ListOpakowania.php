<?php

// app/Filament/Resources/OpakowanieResource/Pages/ListOpakowania.php
namespace App\Filament\Resources\OpakowanieResource\Pages;

use App\Filament\Resources\OpakowanieResource;
use App\Models\Opakowanie;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class ListOpakowania extends ListRecords
{
    protected static string $resource = OpakowanieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj opakowanie')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->size('lg'),
                
            // IMPORT Z EXCEL (.xlsx) - główna akcja
            Actions\Action::make('import_opakowania')
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
                        ->label('Nadpisuj istniejące opakowania')
                        ->helperText('Jeśli kod opakowania już istnieje w bazie, dane zostaną zaktualizowane')
                        ->default(true),
                        
                    Forms\Components\Textarea::make('instrukcje')
                        ->label('Instrukcje')
                        ->default('Format pliku Excel:
Kolumna A: Nazwa opakowania (wymagane)
Kolumna B: Kod opakowania (wymagane - unikalny identyfikator)
Kolumna C: Opis (opcjonalne)
Kolumna D: Pojemność (liczba, np. 250)
Kolumna E: Jednostka (g lub ml)
Kolumna F: Cena (liczba, np. 2.50)

Pierwszy wiersz to nagłówki - zostanie pominięty automatycznie.

WAŻNE: Kod opakowania jest kluczowy - musi być unikalny.
• Jeśli kod już istnieje w bazie, opakowanie zostanie zaktualizowane
• Jeśli kod nie istnieje, zostanie utworzone nowe opakowanie')
                        ->disabled()
                        ->rows(12)
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
                            if (empty(trim($row[0] ?? '')) && empty(trim($row[1] ?? ''))) {
                                continue;
                            }
                            
                            try {
                                $nazwa = trim($row[0] ?? '');
                                $kod = trim($row[1] ?? '');
                                $opis = trim($row[2] ?? '');
                                $pojemnosc = floatval($row[3] ?? 0);
                                $jednostka = strtolower(trim($row[4] ?? 'g'));
                                $cena = floatval($row[5] ?? 0);
                                
                                // Walidacja podstawowych pól
                                if (empty($nazwa)) {
                                    $errors[] = "Wiersz " . ($i + 1) . ": Brak nazwy opakowania";
                                    continue;
                                }
                                
                                if (empty($kod)) {
                                    $errors[] = "Wiersz " . ($i + 1) . ": Brak kodu opakowania (wymagany do identyfikacji)";
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
                                
                                // Walidacja pojemności
                                if ($pojemnosc <= 0) {
                                    $errors[] = "Wiersz " . ($i + 1) . ": Pojemność musi być większa od 0 (podano: {$pojemnosc})";
                                    continue;
                                }
                                
                                // Walidacja jednostki
                                if (!in_array($jednostka, ['g', 'ml'])) {
                                    $errors[] = "Wiersz " . ($i + 1) . ": Nieprawidłowa jednostka '{$jednostka}' (dozwolone: g, ml)";
                                    continue;
                                }
                                
                                // Walidacja ceny
                                if ($cena < 0) {
                                    $errors[] = "Wiersz " . ($i + 1) . ": Cena nie może być ujemna (podano: {$cena})";
                                    continue;
                                }
                                
                                // Przygotuj dane do zapisu
                                $opakownieData = [
                                    'nazwa' => $nazwa,
                                    'kod' => $kod,
                                    'opis' => !empty($opis) ? $opis : null,
                                    'pojemnosc' => $pojemnosc,
                                    'jednostka' => $jednostka,
                                    'cena' => $cena,
                                ];
                                
                                // Sprawdź czy opakowanie już istnieje
                                $existingOpakowanie = Opakowanie::where('kod', $kod)->first();
                                
                                if ($existingOpakowanie) {
                                    if ($updateExisting) {
                                        // Zaktualizuj istniejące opakowanie
                                        $existingOpakowanie->update($opakownieData);
                                        $updated++;
                                    } else {
                                        // Pomiń istniejące opakowanie
                                        $skipped++;
                                        $errors[] = "Wiersz " . ($i + 1) . ": Opakowanie o kodzie '{$kod}' już istnieje - pominięto";
                                    }
                                } else {
                                    // Utwórz nowe opakowanie
                                    Opakowanie::create($opakownieData);
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
                            $message = "📊 PODSUMOWANIE IMPORTU OPAKOWAŃ:\n\n";
                            
                            if ($created > 0) {
                                $message .= "✅ Utworzono nowych opakowań: {$created}\n";
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
                                ->title('Import opakowań zakończony')
                                ->body($message)
                                ->{$notificationType}()
                                ->duration(15000)
                                ->send();
                        } else {
                            throw new \Exception('Nie przetworzono żadnych opakowań. Sprawdź format pliku i dane.');
                        }
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Błąd importu opakowań')
                            ->body('Wystąpił błąd podczas importu: ' . $e->getMessage())
                            ->danger()
                            ->duration(10000)
                            ->send();
                    }
                })
                ->modalHeading('Importuj opakowania z pliku Excel')
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
                            'A1' => 'Nazwa opakowania',
                            'B1' => 'Kod opakowania',
                            'C1' => 'Opis',
                            'D1' => 'Pojemność',
                            'E1' => 'Jednostka',
                            'F1' => 'Cena',
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
                            ['Butelka szklana 250ml', 'BUT-SZ-250', 'Butelka z ciemnego szkła UV', 250, 'ml', 2.50],
                            ['Słoik plastikowy 100g', 'SL-PL-100', 'Słoik z białą nakrętką', 100, 'g', 1.20],
                            ['Butelka plastikowa 500ml', 'BUT-PL-500', 'Butelka HDPE z dozownikiem', 500, 'ml', 3.80],
                            ['Słoik szklany 250g', 'SL-SZ-250', 'Słoik szklany z hermetyczną nakrętką', 250, 'g', 2.20],
                            ['Ampułka 10ml', 'AMP-10', 'Ampułka z ciemnego szkła', 10, 'ml', 0.80],
                            ['Tuba 75ml', 'TUB-75', 'Tuba aluminiowa z zakrętką', 75, 'ml', 1.50],
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
                        $sheet->getComment('B1')->getText()->createTextRun('WYMAGANE: Unikalny kod identyfikujący opakowanie');
                        $sheet->getComment('D1')->getText()->createTextRun('Liczba większa od 0');
                        $sheet->getComment('E1')->getText()->createTextRun('Dozwolone: g, ml');
                        $sheet->getComment('F1')->getText()->createTextRun('Cena w PLN (liczba >= 0)');
                        
                        // Dodaj informacje na dole arkusza
                        $infoRow = $row + 2;
                        $sheet->setCellValue('A' . $infoRow, 'INSTRUKCJE:');
                        $sheet->getStyle('A' . $infoRow)->getFont()->setBold(true);
                        
                        $instructions = [
                            'A. Kolumna "Kod opakowania" jest kluczowa - musi być unikalna',
                            'B. Jeśli kod już istnieje w bazie, opakowanie zostanie zaktualizowane',
                            'C. Jeśli kod nie istnieje, zostanie utworzone nowe opakowanie',
                            'D. Pojemność: liczba większa od 0',
                            'E. Jednostka: tylko "g" (gramy) lub "ml" (mililitry)',
                            'F. Cena: liczba większa lub równa 0',
                            'G. Pierwszy wiersz z nagłówkami zostanie pominięty podczas importu',
                        ];
                        
                        foreach ($instructions as $index => $instruction) {
                            $sheet->setCellValue('A' . ($infoRow + 1 + $index), $instruction);
                            $sheet->getStyle('A' . ($infoRow + 1 + $index))->getFont()->setSize(9);
                        }
                        
                        // Zapisz plik
                        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                        $fileName = 'szablon_opakowania_' . date('Y-m-d_H-i') . '.xlsx';
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
                
            // DODAJ PRZYKŁADOWE OPAKOWANIA
            Actions\Action::make('add_sample_data')
                ->label('Dodaj przykładowe opakowania')
                ->icon('heroicon-o-cube')
                ->color('success')
                ->action(function () {
                    try {
                        $sampleData = [
                            [
                                'nazwa' => 'Butelka szklana 250ml',
                                'kod' => 'BUT-SZ-250',
                                'opis' => 'Butelka z ciemnego szkła UV z dozownikiem',
                                'pojemnosc' => 250,
                                'jednostka' => 'ml',
                                'cena' => 2.50
                            ],
                            [
                                'nazwa' => 'Słoik plastikowy 100g',
                                'kod' => 'SL-PL-100',
                                'opis' => 'Słoik z białą nakrętką',
                                'pojemnosc' => 100,
                                'jednostka' => 'g',
                                'cena' => 1.20
                            ],
                            [
                                'nazwa' => 'Butelka plastikowa 500ml',
                                'kod' => 'BUT-PL-500',
                                'opis' => 'Butelka HDPE z pompką dozownika',
                                'pojemnosc' => 500,
                                'jednostka' => 'ml',
                                'cena' => 3.80
                            ],
                            [
                                'nazwa' => 'Słoik szklany 250g',
                                'kod' => 'SL-SZ-250',
                                'opis' => 'Słoik szklany z hermetyczną nakrętką',
                                'pojemnosc' => 250,
                                'jednostka' => 'g',
                                'cena' => 2.20
                            ],
                            [
                                'nazwa' => 'Ampułka 10ml',
                                'kod' => 'AMP-10',
                                'opis' => 'Ampułka z ciemnego szkła',
                                'pojemnosc' => 10,
                                'jednostka' => 'ml',
                                'cena' => 0.80
                            ],
                            [
                                'nazwa' => 'Tuba 75ml',
                                'kod' => 'TUB-75',
                                'opis' => 'Tuba aluminiowa z zakrętką',
                                'pojemnosc' => 75,
                                'jednostka' => 'ml',
                                'cena' => 1.50
                            ]
                        ];
                        
                        $imported = 0;
                        $skipped = 0;
                        
                        foreach ($sampleData as $data) {
                            // Sprawdź czy opakowanie już istnieje
                            if (Opakowanie::where('kod', $data['kod'])->exists()) {
                                $skipped++;
                                continue;
                            }
                            
                            Opakowanie::create($data);
                            $imported++;
                        }
                        
                        $message = "✅ Dodano {$imported} przykładowych opakowań";
                        if ($skipped > 0) {
                            $message .= "\n⏭️ Pominięto {$skipped} (już istnieją)";
                        }
                        
                        Notification::make()
                            ->title('Przykładowe opakowania dodane')
                            ->body($message)
                            ->success()
                            ->send();
                            
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Błąd dodawania przykładowych opakowań')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Dodaj przykładowe opakowania')
                ->modalDescription('Zostanie dodanych 6 przykładowych opakowań do bazy danych.')
                ->modalSubmitActionLabel('Dodaj'),
        ];
    }
}