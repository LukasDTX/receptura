<?php

namespace App\Services;

use App\Models\Produkt;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductUpdateService
{
    /**
     * Aktualizuje cenę produktu (lokalną)
     */
    public function updatePrice(Produkt $product, float $newPrice, ?string $reason = null): array
    {
        try {
            $oldPrice = $product->cena_sprzedazy;
            
            $product->update([
                'cena_sprzedazy' => $newPrice,
                'updated_at' => now()
            ]);

            Log::info('Product price updated', [
                'product_id' => $product->id,
                'product_name' => $product->nazwa,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            return [
                'success' => true,
                'message' => $reason ? 
                    "Cena zaktualizowana z {$oldPrice} na {$newPrice} PLN. Powód: {$reason}" :
                    "Cena zaktualizowana z {$oldPrice} na {$newPrice} PLN"
            ];

        } catch (Exception $e) {
            Log::error('Error updating product price', [
                'product_id' => $product->id,
                'new_price' => $newPrice,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd podczas aktualizacji ceny: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Aktualizuje ilość produktu (lokalną)
     */
    public function updateQuantity(Produkt $product, int $newQuantity, ?string $reason = null): array
    {
        try {
            $oldQuantity = $product->stan_magazynowy;
            
            $product->update([
                'stan_magazynowy' => $newQuantity,
                'updated_at' => now()
            ]);

            Log::info('Product quantity updated', [
                'product_id' => $product->id,
                'product_name' => $product->nazwa,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            return [
                'success' => true,
                'message' => $reason ? 
                    "Ilość zaktualizowana z {$oldQuantity} na {$newQuantity} szt. Powód: {$reason}" :
                    "Ilość zaktualizowana z {$oldQuantity} na {$newQuantity} szt."
            ];

        } catch (Exception $e) {
            Log::error('Error updating product quantity', [
                'product_id' => $product->id,
                'new_quantity' => $newQuantity,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd podczas aktualizacji ilości: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Aktualizuje cenę BaseLinker produktu
     */
    public function updateBaseLinkerPrice(Produkt $product, float $newPrice, ?string $reason = null): array
    {
        try {
            $oldPrice = $product->baselinker_price;
            
            $product->update([
                'baselinker_price' => $newPrice,
                'updated_at' => now()
            ]);

            Log::info('BaseLinker price updated', [
                'product_id' => $product->id,
                'product_name' => $product->nazwa,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            // Opcjonalnie: automatyczne wysłanie do BaseLinker jeśli sync włączony
            if ($product->baselinker_id && $product->sync_with_baselinker) {
                try {
                    $blService = app(\App\Services\BaseLinkerService::class);
                    $syncResult = $blService->pushToBaseLinker($product);
                    
                    if (!$syncResult['success']) {
                        Log::warning('Failed to sync price to BaseLinker after update', [
                            'product_id' => $product->id,
                            'error' => $syncResult['message']
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Error syncing price to BaseLinker', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => $reason ? 
                    "Cena BaseLinker zaktualizowana z {$oldPrice} na {$newPrice} PLN. Powód: {$reason}" :
                    "Cena BaseLinker zaktualizowana z {$oldPrice} na {$newPrice} PLN"
            ];

        } catch (Exception $e) {
            Log::error('Error updating BaseLinker price', [
                'product_id' => $product->id,
                'new_price' => $newPrice,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd podczas aktualizacji ceny BaseLinker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Aktualizuje ilość BaseLinker produktu
     */
    public function updateBaseLinkerQuantity(Produkt $product, int $newQuantity, ?string $reason = null): array
    {
        try {
            $oldQuantity = $product->baselinker_stock;
            
            $product->update([
                'baselinker_stock' => $newQuantity,
                'updated_at' => now()
            ]);

            Log::info('BaseLinker quantity updated', [
                'product_id' => $product->id,
                'product_name' => $product->nazwa,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            // Opcjonalnie: automatyczne wysłanie do BaseLinker jeśli sync włączony
            if ($product->baselinker_id && $product->sync_with_baselinker) {
                try {
                    $blService = app(\App\Services\BaseLinkerService::class);
                    $syncResult = $blService->pushToBaseLinker($product);
                    
                    if (!$syncResult['success']) {
                        Log::warning('Failed to sync quantity to BaseLinker after update', [
                            'product_id' => $product->id,
                            'error' => $syncResult['message']
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Error syncing quantity to BaseLinker', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => $reason ? 
                    "Ilość BaseLinker zaktualizowana z {$oldQuantity} na {$newQuantity} szt. Powód: {$reason}" :
                    "Ilość BaseLinker zaktualizowana z {$oldQuantity} na {$newQuantity} szt."
            ];

        } catch (Exception $e) {
            Log::error('Error updating BaseLinker quantity', [
                'product_id' => $product->id,
                'new_quantity' => $newQuantity,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd podczas aktualizacji ilości BaseLinker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Masowa aktualizacja cen (lokalnych)
     */
    public function bulkUpdatePrices(array $updates, ?string $reason = null): array
    {
        try {
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($updates as $update) {
                try {
                    $product = Produkt::find($update['product_id']);
                    
                    if (!$product) {
                        $errors[] = "Produkt ID {$update['product_id']} nie znaleziony";
                        $errorCount++;
                        continue;
                    }

                    $result = $this->updatePrice($product, $update['price'], $reason);
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errors[] = "Produkt ID {$update['product_id']}: {$result['message']}";
                        $errorCount++;
                    }

                } catch (Exception $e) {
                    $errors[] = "Produkt ID {$update['product_id']}: {$e->getMessage()}";
                    $errorCount++;
                }
            }

            Log::info('Bulk price update completed', [
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            return [
                'success' => $errorCount === 0,
                'message' => "Zaktualizowano {$successCount} cen" . 
                           ($errorCount > 0 ? ", błędów: {$errorCount}" : ""),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error('Error in bulk price update', [
                'error' => $e->getMessage(),
                'updates_count' => count($updates)
            ]);

            return [
                'success' => false,
                'message' => 'Błąd podczas masowej aktualizacji cen: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Masowa aktualizacja ilości (lokalnych)
     */
    public function bulkUpdateQuantities(array $updates, ?string $reason = null): array
    {
        try {
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($updates as $update) {
                try {
                    $product = Produkt::find($update['product_id']);
                    
                    if (!$product) {
                        $errors[] = "Produkt ID {$update['product_id']} nie znaleziony";
                        $errorCount++;
                        continue;
                    }

                    $result = $this->updateQuantity($product, $update['quantity'], $reason);
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errors[] = "Produkt ID {$update['product_id']}: {$result['message']}";
                        $errorCount++;
                    }

                } catch (Exception $e) {
                    $errors[] = "Produkt ID {$update['product_id']}: {$e->getMessage()}";
                    $errorCount++;
                }
            }

            Log::info('Bulk quantity update completed', [
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            return [
                'success' => $errorCount === 0,
                'message' => "Zaktualizowano {$successCount} ilości" . 
                           ($errorCount > 0 ? ", błędów: {$errorCount}" : ""),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error('Error in bulk quantity update', [
                'error' => $e->getMessage(),
                'updates_count' => count($updates)
            ]);

            return [
                'success' => false,
                'message' => 'Błąd podczas masowej aktualizacji ilości: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Masowa aktualizacja cen BaseLinker
     */
    public function bulkUpdateBaseLinkerPrices(array $updates, ?string $reason = null): array
    {
        try {
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($updates as $update) {
                try {
                    $product = Produkt::find($update['product_id']);
                    
                    if (!$product) {
                        $errors[] = "Produkt ID {$update['product_id']} nie znaleziony";
                        $errorCount++;
                        continue;
                    }

                    $result = $this->updateBaseLinkerPrice($product, $update['price'], $reason);
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errors[] = "Produkt ID {$update['product_id']}: {$result['message']}";
                        $errorCount++;
                    }

                } catch (Exception $e) {
                    $errors[] = "Produkt ID {$update['product_id']}: {$e->getMessage()}";
                    $errorCount++;
                }
            }

            Log::info('Bulk BaseLinker price update completed', [
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            return [
                'success' => $errorCount === 0,
                'message' => "Zaktualizowano {$successCount} cen BaseLinker" . 
                           ($errorCount > 0 ? ", błędów: {$errorCount}" : ""),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error('Error in bulk BaseLinker price update', [
                'error' => $e->getMessage(),
                'updates_count' => count($updates)
            ]);

            return [
                'success' => false,
                'message' => 'Błąd podczas masowej aktualizacji cen BaseLinker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Masowa aktualizacja ilości BaseLinker
     */
    public function bulkUpdateBaseLinkerQuantities(array $updates, ?string $reason = null): array
    {
        try {
            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($updates as $update) {
                try {
                    $product = Produkt::find($update['product_id']);
                    
                    if (!$product) {
                        $errors[] = "Produkt ID {$update['product_id']} nie znaleziony";
                        $errorCount++;
                        continue;
                    }

                    $result = $this->updateBaseLinkerQuantity($product, $update['quantity'], $reason);
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errors[] = "Produkt ID {$update['product_id']}: {$result['message']}";
                        $errorCount++;
                    }

                } catch (Exception $e) {
                    $errors[] = "Produkt ID {$update['product_id']}: {$e->getMessage()}";
                    $errorCount++;
                }
            }

            Log::info('Bulk BaseLinker quantity update completed', [
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            return [
                'success' => $errorCount === 0,
                'message' => "Zaktualizowano {$successCount} ilości BaseLinker" . 
                           ($errorCount > 0 ? ", błędów: {$errorCount}" : ""),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error('Error in bulk BaseLinker quantity update', [
                'error' => $e->getMessage(),
                'updates_count' => count($updates)
            ]);

            return [
                'success' => false,
                'message' => 'Błąd podczas masowej aktualizacji ilości BaseLinker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Kopiuje dane lokalne do BaseLinker dla produktu
     */
    public function copyLocalToBaseLinker(Produkt $product, ?string $reason = null): array
    {
        try {
            $oldBLPrice = $product->baselinker_price;
            $oldBLStock = $product->baselinker_stock;
            
            $product->update([
                'baselinker_price' => $product->cena_sprzedazy,
                'baselinker_stock' => $product->stan_magazynowy,
                'updated_at' => now()
            ]);

            Log::info('Local data copied to BaseLinker', [
                'product_id' => $product->id,
                'product_name' => $product->nazwa,
                'old_bl_price' => $oldBLPrice,
                'new_bl_price' => $product->cena_sprzedazy,
                'old_bl_stock' => $oldBLStock,
                'new_bl_stock' => $product->stan_magazynowy,
                'reason' => $reason,
                'user_id' => auth()->id()
            ]);

            // Opcjonalnie: automatyczne wysłanie do BaseLinker jeśli sync włączony
            if ($product->baselinker_id && $product->sync_with_baselinker) {
                try {
                    $blService = app(\App\Services\BaseLinkerService::class);
                    $syncResult = $blService->pushToBaseLinker($product);
                    
                    if (!$syncResult['success']) {
                        Log::warning('Failed to sync copied data to BaseLinker', [
                            'product_id' => $product->id,
                            'error' => $syncResult['message']
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Error syncing copied data to BaseLinker', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => $reason ? 
                    "Dane lokalne skopiowane do BaseLinker. Powód: {$reason}" :
                    "Dane lokalne skopiowane do BaseLinker"
            ];

        } catch (Exception $e) {
            Log::error('Error copying local data to BaseLinker', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd podczas kopiowania danych lokalnych do BaseLinker: ' . $e->getMessage()
            ];
        }
    }
}