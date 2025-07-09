<?php

// app/Services/StockCalculationService.php
namespace App\Services;

use App\Models\Produkt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class StockCalculationService
{
    /**
     * Oblicza stan magazynowy produktu na podstawie różnych źródeł
     */
    public function calculateStock(Produkt $product): array
    {
        $stockData = [
            'calculated_stock' => 0,
            'manual_stock' => $product->stan_magazynowy ?? 0,
            'baselinker_stock' => $product->baselinker_stock,
            'production_stock' => $this->getProductionStock($product),
            'reserved_stock' => $this->getReservedStock($product),
            'available_stock' => 0,
            'sources' => []
        ];

        // Oblicz dostępny stan = stan magazynowy - zarezerwowany
        $stockData['available_stock'] = max(0, $stockData['manual_stock'] - $stockData['reserved_stock']);
        
        // Ustal ostateczny stan magazynowy
        $stockData['calculated_stock'] = $stockData['available_stock'];
        
        // Informacje o źródłach danych
        $stockData['sources'] = [
            'manual' => 'Stan wprowadzony ręcznie',
            'production' => 'Stan z produkcji',
            'reserved' => 'Stan zarezerwowany',
            'baselinker' => 'Stan z BaseLinker'
        ];

        return $stockData;
    }

    /**
     * Pobiera stan z produkcji (jeśli masz tabele produkcji)
     */
    private function getProductionStock(Produkt $product): int
    {
        // Tutaj możesz dodać logikę obliczania stanu z produkcji
        // Przykład: suma wyprodukowanych jednostek - wydane
        
        try {
            // Sprawdź czy istnieją tabele produkcji
            if (Schema::hasTable('produkcja')) {
                return DB::table('produkcja')
                    ->where('produkt_id', $product->id)
                    ->where('status', 'zakonczona')
                    ->sum('ilosc_wyprodukowana') ?? 0;
            }
        } catch (\Exception $e) {
            Log::warning('Błąd pobierania stanu z produkcji', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }

        return 0;
    }

    /**
     * Pobiera zarezerwowany stan (zamówienia, rezerwacje)
     */
    private function getReservedStock(Produkt $product): int
    {
        try {
            $reserved = 0;

            // Sprawdź czy istnieją tabele z rezerwacjami
            if (Schema::hasTable('rezerwacje')) {
                $reserved += DB::table('rezerwacje')
                    ->where('produkt_id', $product->id)
                    ->where('status', 'aktywna')
                    ->sum('ilosc') ?? 0;
            }

            // Sprawdź zamówienia w trakcie realizacji
            if (Schema::hasTable('zamowienia_pozycje')) {
                $reserved += DB::table('zamowienia_pozycje')
                    ->join('zamowienia', 'zamowienia.id', '=', 'zamowienia_pozycje.zamowienie_id')
                    ->where('zamowienia_pozycje.produkt_id', $product->id)
                    ->whereIn('zamowienia.status', ['nowe', 'w_realizacji', 'spakowane'])
                    ->sum('zamowienia_pozycje.ilosc') ?? 0;
            }

            return $reserved;
        } catch (\Exception $e) {
            Log::warning('Błąd pobierania zarezerwowanego stanu', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Aktualizuje stan magazynowy produktu
     */
    public function updateStock(Produkt $product, int $newStock, string $reason = null): bool
    {
        try {
            $oldStock = $product->stan_magazynowy;
            
            $product->update([
                'stan_magazynowy' => $newStock,
                'updated_at' => now()
            ]);

            // Loguj zmianę stanu
            $this->logStockChange($product, $oldStock, $newStock, $reason);

            return true;
        } catch (\Exception $e) {
            Log::error('Błąd aktualizacji stanu magazynowego', [
                'product_id' => $product->id,
                'old_stock' => $oldStock ?? 0,
                'new_stock' => $newStock,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Dodaje do stanu magazynowego (np. po produkcji)
     */
    public function addStock(Produkt $product, int $quantity, string $reason = null): bool
    {
        $currentStock = $product->stan_magazynowy ?? 0;
        return $this->updateStock($product, $currentStock + $quantity, $reason ?? 'Dodanie do stanu');
    }

    /**
     * Odejmuje ze stanu magazynowego (np. po sprzedaży)
     */
    public function subtractStock(Produkt $product, int $quantity, string $reason = null): bool
    {
        $currentStock = $product->stan_magazynowy ?? 0;
        $newStock = max(0, $currentStock - $quantity);
        return $this->updateStock($product, $newStock, $reason ?? 'Odjęcie ze stanu');
    }

    /**
     * Synchronizuje stan z BaseLinker
     */
    public function syncWithBaseLinker(Produkt $product, BaseLinkerService $baseLinkerService): array
    {
        try {
            if (!$product->baselinker_id) {
                return [
                    'success' => false,
                    'message' => 'Produkt nie ma przypisanego ID BaseLinker'
                ];
            }

            // Pobierz stan z BaseLinker
            $stockData = $baseLinkerService->getInventoryProductsStock([
                'inventory_id' => config('baselinker.default_inventory_id'),
                'filter_id' => $product->baselinker_id
            ]);

            if (empty($stockData)) {
                return [
                    'success' => false,
                    'message' => 'Nie znaleziono produktu w BaseLinker'
                ];
            }

            $blStock = $stockData[0]['stock'] ?? 0;
            $blPrice = $stockData[0]['price_brutto'] ?? null;

            // Aktualizuj dane z BaseLinker
            $product->update([
                'baselinker_stock' => $blStock,
                'baselinker_price' => $blPrice,
                'last_baselinker_sync' => now()
            ]);

            return [
                'success' => true,
                'baselinker_stock' => $blStock,
                'local_stock' => $product->stan_magazynowy,
                'difference' => $product->stan_magazynowy - $blStock,
                'message' => "Stan BaseLinker: {$blStock}"
            ];

        } catch (\Exception $e) {
            Log::error('Błąd synchronizacji z BaseLinker', [
                'product_id' => $product->id,
                'baselinker_id' => $product->baselinker_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd synchronizacji: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Wysyła lokalny stan do BaseLinker
     */
    public function pushToBaseLinker(Produkt $product, BaseLinkerService $baseLinkerService): array
    {
        try {
            if (!$product->baselinker_id) {
                return [
                    'success' => false,
                    'message' => 'Produkt nie ma przypisanego ID BaseLinker'
                ];
            }

            $stockData = [
                'inventory_id' => config('baselinker.default_inventory_id'),
                'warehouse_id' => config('baselinker.default_warehouse_id'),
                'stock' => $product->stan_magazynowy ?? 0,
                'price' => $product->cena_sprzedazy,
            ];

            $result = $baseLinkerService->updateInventoryProductStock(
                $product->baselinker_id,
                $stockData
            );

            $product->update(['last_baselinker_sync' => now()]);

            return [
                'success' => true,
                'sent_stock' => $stockData['stock'],
                'message' => "Wysłano stan: {$stockData['stock']} do BaseLinker"
            ];

        } catch (\Exception $e) {
            Log::error('Błąd wysyłania stanu do BaseLinker', [
                'product_id' => $product->id,
                'baselinker_id' => $product->baselinker_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd wysyłania: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pobiera produkty z niskim stanem
     */
    public function getLowStockProducts(int $threshold = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Produkt::where('stan_magazynowy', '<=', $threshold)
            ->where('stan_magazynowy', '>', 0)
            ->orderBy('stan_magazynowy', 'asc')
            ->get();
    }

    /**
     * Pobiera produkty bez stanu
     */
    public function getOutOfStockProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return Produkt::where('stan_magazynowy', '<=', 0)
            ->orderBy('nazwa', 'asc')
            ->get();
    }

    /**
     * Pobiera produkty z różnicami w stanach (lokalny vs BaseLinker)
     */
    public function getStockMismatchProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return Produkt::whereNotNull('baselinker_id')
            ->where('baselinker_id', '!=', '')
            ->whereNotNull('baselinker_stock')
            ->whereRaw('stan_magazynowy != baselinker_stock')
            ->orderBy('nazwa', 'asc')
            ->get();
    }

    /**
     * Generuje raport stanu magazynowego
     */
    public function generateStockReport(): array
    {
        $total = Produkt::count();
        $inStock = Produkt::where('stan_magazynowy', '>', 5)->count();
        $lowStock = Produkt::whereBetween('stan_magazynowy', [1, 5])->count();
        $outOfStock = Produkt::where('stan_magazynowy', '<=', 0)->count();
        $withBaseLinker = Produkt::whereNotNull('baselinker_id')
            ->where('baselinker_id', '!=', '')
            ->count();
        $stockMismatches = $this->getStockMismatchProducts()->count();

        return [
            'summary' => [
                'total_products' => $total,
                'in_stock' => $inStock,
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
                'with_baselinker' => $withBaseLinker,
                'stock_mismatches' => $stockMismatches,
            ],
            'percentages' => [
                'in_stock_percent' => $total > 0 ? round(($inStock / $total) * 100, 1) : 0,
                'low_stock_percent' => $total > 0 ? round(($lowStock / $total) * 100, 1) : 0,
                'out_of_stock_percent' => $total > 0 ? round(($outOfStock / $total) * 100, 1) : 0,
            ],
            'alerts' => [
                'critical' => $outOfStock,
                'warning' => $lowStock,
                'mismatch' => $stockMismatches,
            ]
        ];
    }

    /**
     * Loguje zmiany stanu magazynowego
     */
    private function logStockChange(Produkt $product, int $oldStock, int $newStock, ?string $reason): void
    {
        try {
            // Sprawdź czy istnieje tabela logów
            if (Schema::hasTable('stock_changes_log')) {
                DB::table('stock_changes_log')->insert([
                    'product_id' => $product->id,
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'difference' => $newStock - $oldStock,
                    'reason' => $reason,
                    'user_id' => auth()->id(),
                    'created_at' => now(),
                ]);
            }

            // Loguj w Laravel logs
            Log::info('Stock change', [
                'product_id' => $product->id,
                'product_name' => $product->nazwa,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'difference' => $newStock - $oldStock,
                'reason' => $reason,
                'user_id' => auth()->id(),
            ]);

        } catch (\Exception $e) {
            Log::error('Błąd logowania zmiany stanu', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}