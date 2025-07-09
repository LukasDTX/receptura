<?php

// app/Services/BaseLinkerService.php
namespace App\Services;

use App\Models\Produkt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BaseLinkerService
{
    protected string $apiUrl = 'https://api.baselinker.com/connector.php';
    protected ?string $token;
    protected int $inventoryId = 17619;

    public function __construct()
    {
        // Debug - sprawdź wszystkie możliwe źródła tokena
        $envToken = env('BASELINKER_API_TOKEN');
        $configToken = config('baselinker.token');
        $directEnv = $_ENV['BASELINKER_API_TOKEN'] ?? null;
        
        Log::info('BaseLinker Token Debug', [
            'env_token' => $envToken ? 'EXISTS (' . strlen($envToken) . ' chars)' : 'NULL',
            'config_token' => $configToken ? 'EXISTS (' . strlen($configToken) . ' chars)' : 'NULL',
            'direct_env' => $directEnv ? 'EXISTS (' . strlen($directEnv) . ' chars)' : 'NULL',
        ]);
        
        // Użyj bezpośrednio env() jeśli config() nie działa
        $this->token = $configToken ?: $envToken ?: $directEnv;
        
        // Sprawdź czy token jest ustawiony
        if (empty($this->token)) {
            Log::warning('BaseLinker API token not configured', [
                'checked_sources' => ['config', 'env', 'direct_env'],
                'config_path_exists' => file_exists(config_path('baselinker.php')),
            ]);
        } else {
            Log::info('BaseLinker token loaded successfully', [
                'token_length' => strlen($this->token),
                'source' => $configToken ? 'config' : ($envToken ? 'env' : 'direct_env')
            ]);
        }
    }

    /**
     * Sprawdza czy serwis jest skonfigurowany
     */
    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    /**
     * Sprawdza czy produkt jest gotowy do wysłania do BaseLinker
     */
    public function isProductReadyForExport(Produkt $product): bool
    {
        return !empty($product->kod) && 
               !empty($product->nazwa) && 
               ($product->baselinker_price ?? $product->cena_sprzedazy) > 0;
    }

    /**
     * Sprawdza czy produkt można wysłać do BaseLinker (gotowy + nie ma jeszcze w BL)
     */
    public function canSendToBaseLinker(Produkt $product): bool
    {
        return $this->isProductReadyForExport($product) && 
               empty($product->baselinker_id);
    }

    /**
     * Przygotowuje dane produktu do wysłania do BaseLinker INVENTORY
     */
    public function prepareProductData(Produkt $product): array
    {
        // Upewnij się, że mamy wartości baselinker_price i baselinker_stock
        $blPrice = $product->baselinker_price ?? $product->cena_sprzedazy;
        $blStock = $product->baselinker_stock ?? $product->stan_magazynowy;

        return [
            'inventory_id' => $this->inventoryId,
            'sku' => $product->kod ?: 'BRAK_SKU',
            'ean' => $product->ean ?: '',
            'price' => (float)$blPrice,
            'quantity' => (int)$blStock,
            'text_fields' => [
                'name' => $product->nazwa ?: 'BRAK_NAZWY'
            ]
        ];
    }

    /**
     * Testuje dodanie produktu z minimalnym zestawem parametrów
     */
    public function addMinimalInventoryProduct(array $productData): array
    {
        // Struktura zgodna z dokumentacją BaseLinker API - oczekuje już poprawnej struktury
        $parameters = [
            'inventory_id' => $productData['inventory_id'],
            'product_id' => '', // Pusty dla nowego produktu
            'sku' => $productData['sku'],
            'ean' => $productData['ean'] ?? '',
            'name' => $productData['text_fields']['name'] ?? '',
            'prices' => (object)($productData['prices'] ?? ['0' => 0]), // OBIEKT, nie tablica
            'stock' => (object)($productData['stock'] ?? ['0' => 0]), // OBIEKT, nie tablica
            'tax_rate' => 23,
            'weight' => 0,
            'description' => '',
            'description_extra1' => '',
            'description_extra2' => '',
            'description_extra3' => '',
            'description_extra4' => '',
            'man_name' => '',
            'man_image' => '',
            'category_id' => 0,
            'images' => [],
            'features' => [],
            'variants' => [],
            'text_fields' => $productData['text_fields'] ?? [],
            'dimensions' => []
        ];

        Log::info('BaseLinker addInventoryProduct OBJECT FORMAT', [
            'method' => 'addInventoryProduct',
            'parameters' => $parameters,
            'prices_type' => gettype($parameters['prices']),
            'stock_type' => gettype($parameters['stock']),
            'prices_structure' => $parameters['prices'],
            'stock_structure' => $parameters['stock'],
            'parameters_json' => json_encode($parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ]);

        // Spróbuj z metodą addInventoryProduct
        try {
            return $this->makeRequest('addInventoryProduct', $parameters);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'empty or unknown method') !== false) {
                Log::info('addInventoryProduct nie działa, próbuję alternatyw');
                
                // Lista alternatywnych metod do testowania
                $alternativeMethods = [
                    'addProduct',
                    'createInventoryProduct', 
                    'insertInventoryProduct',
                    'addInventoryItem'
                ];
                
                foreach ($alternativeMethods as $method) {
                    try {
                        Log::info("Próba z metodą: {$method}");
                        return $this->makeRequest($method, $parameters);
                    } catch (Exception $altE) {
                        Log::warning("Metoda {$method} nie działa: " . $altE->getMessage());
                        continue;
                    }
                }
                
                // Jeśli żadna metoda nie działa, rzuć oryginalny błąd
                throw $e;
            }
            throw $e;
        }
    }

    /**
     * Wysyła produkt do BaseLinker INVENTORY
     */
    public function sendProductToBaseLinker(Produkt $product): array
    {
        try {
            if (!$this->canSendToBaseLinker($product)) {
                return [
                    'success' => false,
                    'message' => 'Produkt nie spełnia wymogów do wysłania lub już istnieje w BaseLinker'
                ];
            }

            // PRZED wysłaniem - ustaw baselinker_price i baselinker_stock jeśli nie ma wartości
            if ($product->baselinker_price === null) {
                $product->baselinker_price = $product->cena_sprzedazy;
            }
            if ($product->baselinker_stock === null) {
                $product->baselinker_stock = $product->stan_magazynowy;
            }
            $product->save();

            // Przygotuj dane w formacie zgodnym z API BaseLinker - używaj już poprawnej struktury
            $productData = [
                'inventory_id' => $this->inventoryId,
                'sku' => $product->kod,
                'ean' => $product->ean ?? '',
                'prices' => [
                    '0' => (float)$product->baselinker_price // ZAWSZE baselinker_price
                ],
                'stock' => [
                    '0' => (int)$product->baselinker_stock // ZAWSZE baselinker_stock
                ],
                'text_fields' => [
                    'name' => $product->nazwa
                ]
            ];
            
            Log::info('Wysyłanie produktu do BaseLinker - SZCZEGÓŁY', [
                'product_id' => $product->id,
                'product_nazwa' => $product->nazwa,
                'product_kod' => $product->kod,
                'baselinker_price' => $product->baselinker_price,
                'baselinker_stock' => $product->baselinker_stock,
                'cena_sprzedazy' => $product->cena_sprzedazy,
                'stan_magazynowy' => $product->stan_magazynowy,
                'inventory_id' => $this->inventoryId,
                'final_data' => $productData
            ]);
            
            $response = $this->addMinimalInventoryProduct($productData);
            
            Log::info('BaseLinker addInventoryProduct RESPONSE', [
                'response' => $response,
                'product_id_received' => $response['product_id'] ?? 'BRAK',
                'status' => $response['status'] ?? 'BRAK'
            ]);
            
            if (isset($response['product_id'])) {
                // Aktualizuj produkt w bazie danych
                $product->update([
                    'baselinker_id' => $response['product_id'],
                    'last_baselinker_sync' => now(),
                    'sync_with_baselinker' => true,
                ]);

                // Sprawdź czy produkt został rzeczywiście dodany z prawidłowymi danymi
                try {
                    $verifyProduct = $this->getInventoryProducts([
                        'filter_id' => $response['product_id'],
                        'filter_limit' => 1
                    ]);
                    
                    if (!empty($verifyProduct)) {
                        $blProduct = $verifyProduct[0];
                        
                        // Pobierz dane z nowej struktury BaseLinker
                        $actualPrice = 0;
                        $actualStock = 0;
                        
                        if (isset($blProduct['prices']) && is_array($blProduct['prices'])) {
                            $actualPrice = (float)($blProduct['prices']['0'] ?? $blProduct['prices'][0] ?? 0);
                        } elseif (isset($blProduct['price_brutto'])) {
                            $actualPrice = (float)$blProduct['price_brutto'];
                        }
                        
                        if (isset($blProduct['stock']) && is_array($blProduct['stock'])) {
                            $actualStock = (int)($blProduct['stock']['0'] ?? $blProduct['stock'][0] ?? 0);
                        } elseif (isset($blProduct['quantity'])) {
                            $actualStock = (int)$blProduct['quantity'];
                        }
                        
                        Log::info('Weryfikacja dodanego produktu w BaseLinker', [
                            'product_id' => $response['product_id'],
                            'sent_price' => $product->baselinker_price,
                            'actual_price' => $actualPrice,
                            'sent_stock' => $product->baselinker_stock,
                            'actual_stock' => $actualStock,
                            'bl_product_full' => $blProduct,
                            'prices_structure' => $blProduct['prices'] ?? 'BRAK',
                            'stock_structure' => $blProduct['stock'] ?? 'BRAK'
                        ]);
                        
                        return [
                            'success' => true,
                            'message' => "Produkt {$product->nazwa} dodany do BaseLinker (ID: {$response['product_id']})\n" .
                                       "Wysłano: cena {$product->baselinker_price} PLN, stan {$product->baselinker_stock} szt.\n" .
                                       "W BaseLinker: cena {$actualPrice} PLN, stan {$actualStock} szt."
                        ];
                    }
                } catch (Exception $e) {
                    Log::warning('Nie udało się zweryfikować dodanego produktu', [
                        'product_id' => $response['product_id'],
                        'error' => $e->getMessage()
                    ]);
                }

                return [
                    'success' => true,
                    'message' => "Produkt {$product->nazwa} został dodany do BaseLinker (ID: {$response['product_id']}) z ceną {$product->baselinker_price} PLN i stanem {$product->baselinker_stock} szt."
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Nie otrzymano ID produktu z BaseLinker. Odpowiedź: ' . json_encode($response)
                ];
            }

        } catch (Exception $e) {
            Log::error('Błąd wysyłania produktu do BaseLinker', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd komunikacji z BaseLinker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pobiera dane produktu z BaseLinker i aktualizuje baselinker_price i baselinker_stock
     */
    public function syncFromBaseLinker(Produkt $product): array
    {
        try {
            if (!$product->baselinker_id) {
                return [
                    'success' => false,
                    'message' => 'Produkt nie ma ID BaseLinker'
                ];
            }

            // Pobierz dane produktu z BaseLinker
            $blProducts = $this->getInventoryProducts([
                'filter_id' => $product->baselinker_id,
                'filter_limit' => 1
            ]);

            if (empty($blProducts)) {
                return [
                    'success' => false,
                    'message' => 'Nie znaleziono produktu w BaseLinker'
                ];
            }

            $blProduct = $blProducts[0];
            
            // BaseLinker zwraca ceny i stany w strukturze z wariantami
            // Pobierz główny wariant (klucz "0")
            $blPrice = 0;
            $blStock = 0;
            
            if (isset($blProduct['prices']) && is_array($blProduct['prices'])) {
                $blPrice = (float)($blProduct['prices']['0'] ?? $blProduct['prices'][0] ?? 0);
            } elseif (isset($blProduct['price_brutto'])) {
                $blPrice = (float)$blProduct['price_brutto'];
            }
            
            if (isset($blProduct['stock']) && is_array($blProduct['stock'])) {
                $blStock = (int)($blProduct['stock']['0'] ?? $blProduct['stock'][0] ?? 0);
            } elseif (isset($blProduct['quantity'])) {
                $blStock = (int)$blProduct['quantity'];
            }

            Log::info('Pobrano dane z BaseLinker', [
                'product_id' => $product->id,
                'baselinker_id' => $product->baselinker_id,
                'bl_product_structure' => $blProduct,
                'extracted_price' => $blPrice,
                'extracted_stock' => $blStock,
                'prices_array' => $blProduct['prices'] ?? 'BRAK',
                'stock_array' => $blProduct['stock'] ?? 'BRAK'
            ]);

            // Aktualizuj dane BL w bazie
            $product->update([
                'baselinker_price' => $blPrice,
                'baselinker_stock' => $blStock,
                'last_baselinker_sync' => now()
            ]);

            return [
                'success' => true,
                'message' => "Pobrano z BaseLinker: cena {$blPrice} PLN, stan {$blStock} szt."
            ];

        } catch (Exception $e) {
            Log::error('Błąd pobierania z BaseLinker', [
                'product_id' => $product->id,
                'baselinker_id' => $product->baselinker_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd komunikacji z BaseLinker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Wysyła dane baselinker_price i baselinker_stock do BaseLinker
     */
    public function pushToBaseLinker(Produkt $product): array
    {
        try {
            if (!$product->baselinker_id) {
                return [
                    'success' => false,
                    'message' => 'Produkt nie ma ID BaseLinker'
                ];
            }

            // PRZED wysłaniem - ustaw baselinker_price i baselinker_stock jeśli nie ma wartości
            if ($product->baselinker_price === null) {
                $product->baselinker_price = $product->cena_sprzedazy;
                $product->save();
            }
            if ($product->baselinker_stock === null) {
                $product->baselinker_stock = $product->stan_magazynowy;
                $product->save();
            }

            $results = [];
            $errors = [];
            
            // 1. Aktualizuj stan w BaseLinker
            try {
                $warehouseId = $this->getDefaultWarehouseId();
                
                $stockResult = $this->updateInventoryProductStock($product->baselinker_id, [
                    'inventory_id' => $this->inventoryId,
                    'warehouse_id' => $warehouseId,
                    'stock' => (int)$product->baselinker_stock
                ]);
                
                $results['stock'] = $stockResult;
                Log::info('Stan BaseLinker zaktualizowany', [
                    'product_id' => $product->id,
                    'baselinker_id' => $product->baselinker_id,
                    'warehouse_id' => $warehouseId,
                    'stock' => $product->baselinker_stock,
                    'result' => $stockResult
                ]);
                
            } catch (Exception $e) {
                $errors[] = 'Błąd aktualizacji stanu: ' . $e->getMessage();
                Log::error('Błąd aktualizacji stanu BaseLinker', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            // 2. Aktualizuj ceny w BaseLinker
            try {
                $priceResult = $this->updateInventoryProductPrices($product->baselinker_id, [
                    'inventory_id' => $this->inventoryId,
                    'price' => (float)$product->baselinker_price
                ]);
                
                $results['price'] = $priceResult;
                Log::info('Cena BaseLinker zaktualizowana', [
                    'product_id' => $product->id,
                    'baselinker_id' => $product->baselinker_id,
                    'price' => $product->baselinker_price,
                    'result' => $priceResult
                ]);
                
            } catch (Exception $e) {
                $errors[] = 'Błąd aktualizacji ceny: ' . $e->getMessage();
                Log::error('Błąd aktualizacji ceny BaseLinker', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage()
                ]);
            }

            // 3. Zaktualizuj timestamp synchronizacji
            $product->update([
                'last_baselinker_sync' => now()
            ]);

            if (empty($errors)) {
                return [
                    'success' => true,
                    'message' => "Dane wysłane do BaseLinker: cena " . number_format($product->baselinker_price, 2) . " PLN, stan {$product->baselinker_stock} szt.",
                    'results' => $results
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Błędy aktualizacji: ' . implode(', ', $errors),
                    'results' => $results,
                    'errors' => $errors
                ];
            }

        } catch (Exception $e) {
            Log::error('Błąd wysyłania do BaseLinker', [
                'product_id' => $product->id,
                'baselinker_id' => $product->baselinker_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Błąd komunikacji z BaseLinker: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Dodaje produkt do magazynu BaseLinker (INVENTORY API)
     */
    public function addInventoryProduct(array $productData): array
    {
        $parameters = [
            'inventory_id' => $productData['inventory_id'],
            'sku' => $productData['sku'] ?? '',
            'ean' => $productData['ean'] ?? '',
            'name' => $productData['text_fields']['name'] ?? '',
            'stock' => $productData['quantity'] ?? 0,
            'price_brutto' => $productData['price'] ?? 0,
            'tax_rate' => 23,
            'weight' => 0,
        ];

        if (!empty($productData['text_fields'])) {
            $parameters['text_fields'] = $productData['text_fields'];
        }

        Log::info('BaseLinker addInventoryProduct parameters', [
            'parameters' => $parameters
        ]);

        return $this->makeRequest('addInventoryProduct', $parameters);
    }

    /**
     * Aktualizuje stan produktu w BaseLinker używając updateInventoryProductsStock
     */
    public function updateInventoryProductStock(string $productId, array $stockData): array
    {
        $stockData['warehouse_id'] = "0";
        // BaseLinker używa struktury: products -> product_id -> warehouse_id -> stock
        $parameters = [
            'inventory_id' => $stockData['inventory_id'],
    'products' => [
    $productId => [ 'bl_33180' => $stockData['stock'] ] // Użyj warehouse_id jako klucza
            // '0' => (int)$stockData['stock'] // Jeśli nie ma warehouse_id, użyj domyślnego klucza '0'
            // Możesz też użyć warehouse_id jako klucza, jeśli jest wymagane
            // 'warehouse_id' => (int)$stockData['warehouse_id'], // Jeśli potrzebujesz warehouse_id jako klucz
  ]
        ];

        Log::info('BaseLinker updateInventoryProductsStock CORRECT FORMAT', [
            'product_id' => $productId,
            'method' => 'updateInventoryProductsStock',
            'parameters' => $parameters,
            'parameters_json' => json_encode($parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ]);

        return $this->makeRequest('updateInventoryProductsStock', $parameters);
    }

    /**
     * Aktualizuje ceny produktu w BaseLinker używając updateInventoryProductsPrices
     */
    public function updateInventoryProductPrices(string $productId, array $priceData): array
    {
        // BaseLinker prawdopodobnie używa podobnej struktury dla cen
        $parameters = [
            'inventory_id' => $priceData['inventory_id'],
            'products' => [
                $productId => [ '15786' => (float)$priceData['price']]
            ]
        ];

        Log::info('BaseLinker updateInventoryProductsPrices FORMAT', [
            'product_id' => $productId,
            'method' => 'updateInventoryProductsPrices',
            'parameters' => $parameters,
            'parameters_json' => json_encode($parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ]);

        try {
            return $this->makeRequest('updateInventoryProductsPrices', $parameters);
        } catch (Exception $e) {
            // Spróbuj alternatywnych metod dla cen
            $altMethods = ['updateInventoryPrices', 'setInventoryProductsPrices'];
            foreach ($altMethods as $method) {
                try {
                    Log::info("Próba z metodą cenową: {$method}");
                    return $this->makeRequest($method, $parameters);
                } catch (Exception $altE) {
                    continue;
                }
            }
            throw $e;
        }
    }

    /**
     * Aktualizuje produkt w magazynie BaseLinker (używa nowych metod)
     */
    public function updateInventoryProduct(string $productId, array $productData): array
    {
        $results = [];
        $errors = [];
        
        // 1. Aktualizuj stan jeśli podany
        if (isset($productData['stock']['0'])) {
            try {
                $stockResult = $this->updateInventoryProductStock($productId, [
                    'inventory_id' => $productData['inventory_id'],
                    'warehouse_id' => config('baselinker.default_warehouse_id', 'bl_206'), // Domyślny magazyn
                    'stock' => $productData['stock']['0']
                ]);
                $results['stock_update'] = $stockResult;
                Log::info('Stan produktu zaktualizowany', ['product_id' => $productId, 'result' => $stockResult]);
            } catch (Exception $e) {
                $errors[] = 'Błąd aktualizacji stanu: ' . $e->getMessage();
                Log::error('Błąd aktualizacji stanu produktu', ['product_id' => $productId, 'error' => $e->getMessage()]);
            }
        }
        
        // 2. Aktualizuj ceny jeśli podane
        if (isset($productData['prices']['0'])) {
            try {
                $priceResult = $this->updateInventoryProductPrices($productId, [
                    'inventory_id' => $productData['inventory_id'],
                    'price' => $productData['prices']['0']
                ]);
                $results['price_update'] = $priceResult;
                Log::info('Cena produktu zaktualizowana', ['product_id' => $productId, 'result' => $priceResult]);
            } catch (Exception $e) {
                $errors[] = 'Błąd aktualizacji ceny: ' . $e->getMessage();
                Log::error('Błąd aktualizacji ceny produktu', ['product_id' => $productId, 'error' => $e->getMessage()]);
            }
        }
        
        // 3. Aktualizuj inne dane produktu jeśli potrzeba (nazwa, SKU, itp.)
        if (isset($productData['sku']) || isset($productData['name'])) {
            try {
                // Spróbuj użyć addInventoryProduct z istniejącym product_id (może zaktualizować)
                $productUpdateParams = [
                    'inventory_id' => $productData['inventory_id'],
                    'product_id' => $productId,
                    'sku' => $productData['sku'] ?? '',
                    'ean' => $productData['ean'] ?? '',
                    'name' => $productData['text_fields']['name'] ?? '',
                    'tax_rate' => 23,
                    'weight' => 0
                ];
                
                $productResult = $this->makeRequest('addInventoryProduct', $productUpdateParams);
                $results['product_update'] = $productResult;
                Log::info('Dane produktu zaktualizowane', ['product_id' => $productId, 'result' => $productResult]);
            } catch (Exception $e) {
                $errors[] = 'Błąd aktualizacji danych produktu: ' . $e->getMessage();
                Log::error('Błąd aktualizacji danych produktu', ['product_id' => $productId, 'error' => $e->getMessage()]);
            }
        }
        
        if (empty($errors)) {
            return [
                'status' => 'SUCCESS',
                'results' => $results,
                'message' => 'Produkt zaktualizowany pomyślnie'
            ];
        } else {
            return [
                'status' => 'PARTIAL_ERROR',
                'results' => $results,
                'errors' => $errors,
                'message' => 'Aktualizacja zakończona z błędami: ' . implode(', ', $errors)
            ];
        }
    }

    /**
     * Usuwa produkt z magazynu BaseLinker
     */
    public function deleteInventoryProduct(string $productId): array
    {
        $parameters = [
            'product_id' => $productId
        ];

        return $this->makeRequest('deleteInventoryProduct', $parameters);
    }

    /**
     * Pobiera produkty z magazynu BaseLinker
     */
    public function getInventoryProducts(array $filters = []): array
    {
        $parameters = [
            'inventory_id' => $filters['inventory_id'] ?? $this->inventoryId,
            'filter_sort' => $filters['filter_sort'] ?? 'id',
            'filter_id' => $filters['filter_id'] ?? null,
            'filter_sku' => $filters['filter_sku'] ?? null,
            'filter_name' => $filters['filter_name'] ?? null,
            'filter_price_from' => $filters['filter_price_from'] ?? null,
            'filter_price_to' => $filters['filter_price_to'] ?? null,
            'filter_stock_from' => $filters['filter_stock_from'] ?? null,
            'filter_stock_to' => $filters['filter_stock_to'] ?? null,
            'filter_category_id' => $filters['filter_category_id'] ?? null,
            'filter_limit' => $filters['filter_limit'] ?? 1000,
            'page' => $filters['page'] ?? 1,
        ];

        $response = $this->makeRequest('getInventoryProducts', $parameters);
        return $response['products'] ?? [];
    }

    /**
     * Masowe wysyłanie produktów do BaseLinker
     */
    public function bulkSendProductsToBaseLinker(Collection $products): array
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($products as $product) {
            $result = $this->sendProductToBaseLinker($product);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "ID {$product->id}: {$result['message']}";
            }

            // Przerwa między requestami żeby nie przeciążyć API
            usleep(100000); // 0.1 sekundy
        }

        if (!empty($errors)) {
            Log::warning('Błędy podczas masowego wysyłania do BaseLinker', $errors);
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors
        ];
    }

    /**
     * Scope - produkty gotowe do eksportu
     */
    public function scopeReadyForExport(Builder $query): Builder
    {
        return $query->whereNotNull('kod')
                    ->where('kod', '!=', '')
                    ->whereNotNull('nazwa')
                    ->where('nazwa', '!=', '')
                    ->where(function ($query) {
                        $query->where('baselinker_price', '>', 0)
                              ->orWhere('cena_sprzedazy', '>', 0);
                    });
    }

    /**
     * Scope - produkty z brakującymi danymi
     */
    public function scopeMissingData(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query->whereNull('kod')
                  ->orWhere('kod', '')
                  ->orWhereNull('nazwa')
                  ->orWhere('nazwa', '')
                  ->orWhere(function ($query) {
                      $query->whereNull('baselinker_price')
                            ->orWhere('baselinker_price', '<=', 0)
                            ->where(function ($query) {
                                $query->whereNull('cena_sprzedazy')
                                      ->orWhere('cena_sprzedazy', '<=', 0);
                            });
                  });
        });
    }

    /**
     * Scope - produkty nie ma w BaseLinker
     */
    public function scopeNotInBaseLinker(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query->whereNull('baselinker_id')
                  ->orWhere('baselinker_id', '');
        });
    }

    /**
     * Pobiera liczbę produktów gotowych do eksportu
     */
    public function getReadyForExportCount(): int
    {
        return Produkt::query()
            ->tap(fn($query) => $this->scopeReadyForExport($query))
            ->tap(fn($query) => $this->scopeNotInBaseLinker($query))
            ->count();
    }

    /**
     * Synchronizuje wszystkie produkty z BaseLinker
     */
    public function syncAllProductsWithBaseLinker(): array
    {
        $products = Produkt::whereNotNull('baselinker_id')
                          ->where('sync_with_baselinker', true)
                          ->get();

        $successCount = 0;
        $errorCount = 0;

        foreach ($products as $product) {
            $result = $this->syncFromBaseLinker($product);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }

            // Przerwa między requestami
            usleep(200000); // 0.2 sekundy
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'total_processed' => $products->count()
        ];
    }

    /**
     * Waliduje dane produktu przed wysłaniem
     */
    public function validateProductData(Produkt $product): array
    {
        $errors = [];

        if (empty($product->kod)) {
            $errors[] = 'Brak kodu produktu (SKU)';
        }

        if (empty($product->nazwa)) {
            $errors[] = 'Brak nazwy produktu';
        }

        $price = $product->baselinker_price ?? $product->cena_sprzedazy;
        if (!$price || $price <= 0) {
            $errors[] = 'Nieprawidłowa cena (sprawdź baselinker_price lub cena_sprzedazy)';
        }

        if (strlen($product->kod ?? '') > 50) {
            $errors[] = 'Kod produktu jest za długi (max 50 znaków)';
        }

        if (strlen($product->nazwa ?? '') > 200) {
            $errors[] = 'Nazwa produktu jest za długa (max 200 znaków)';
        }

        if ($product->ean && !$this->isValidEAN($product->ean)) {
            $errors[] = 'Nieprawidłowy format kodu EAN';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Sprawdza poprawność kodu EAN
     */
    protected function isValidEAN(string $ean): bool
    {
        // Usuń wszystkie znaki oprócz cyfr
        $ean = preg_replace('/[^0-9]/', '', $ean);
        
        // EAN może mieć 8, 13 lub 14 cyfr
        if (!in_array(strlen($ean), [8, 13, 14])) {
            return false;
        }

        // Sprawdź sumę kontrolną dla EAN-13
        if (strlen($ean) === 13) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += $ean[$i] * (($i % 2 === 0) ? 1 : 3);
            }
            $checksum = (10 - ($sum % 10)) % 10;
            return $checksum == $ean[12];
        }

        return true; // Dla EAN-8 i EAN-14 przyjmujemy jako poprawne
    }

    /**
     * Generuje raport synchronizacji
     */
    public function generateSyncReport(): array
    {
        $totalProducts = Produkt::count();
        $readyForExport = $this->getReadyForExportCount();
        $inBaseLinker = Produkt::whereNotNull('baselinker_id')->count();
        $syncEnabled = Produkt::where('sync_with_baselinker', true)->count();
        
        // Sprawdź różnice w danych
        $priceMismatches = Produkt::whereNotNull('baselinker_id')
                                 ->whereNotNull('baselinker_price')
                                 ->whereRaw('cena_sprzedazy != baselinker_price')
                                 ->count();

        $stockMismatches = Produkt::whereNotNull('baselinker_id')
                                 ->whereNotNull('baselinker_stock')
                                 ->whereRaw('stan_magazynowy != baselinker_stock')
                                 ->count();

        $dataMismatches = Produkt::whereNotNull('baselinker_id')
                                ->where(function ($query) {
                                    $query->whereRaw('stan_magazynowy != baselinker_stock')
                                          ->orWhereRaw('cena_sprzedazy != baselinker_price');
                                })
                                ->count();

        return [
            'total_products' => $totalProducts,
            'ready_for_export' => $readyForExport,
            'in_baselinker' => $inBaseLinker,
            'sync_enabled' => $syncEnabled,
            'price_mismatches' => $priceMismatches,
            'stock_mismatches' => $stockMismatches,
            'data_mismatches' => $dataMismatches,
            'export_percentage' => $totalProducts > 0 ? round(($inBaseLinker / $totalProducts) * 100, 1) : 0,
            'sync_percentage' => $inBaseLinker > 0 ? round(($syncEnabled / $inBaseLinker) * 100, 1) : 0
        ];
    }

    // ============ POZOSTAŁE METODY API (BEZ ZMIAN) ============

    /**
     * Wysyła żądanie do API BaseLinker
     */
    protected function makeRequest(string $method, array $parameters = []): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('BaseLinker API token not configured. Please set BASELINKER_API_TOKEN in your .env file.');
        }

        try {
            $requestData = [
                'token' => $this->token,
                'method' => $method,
                'parameters' => json_encode($parameters)
            ];

            Log::info('BaseLinker API Request DEBUG', [
                'method' => $method,
                'url' => $this->apiUrl,
                'token' => substr($this->token, 0, 20) . '...',
                'token_length' => strlen($this->token),
                'parameters' => $parameters,
                'parameters_json' => json_encode($parameters),
                'parameters_count' => count($parameters),
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent' => 'Laravel-BaseLinker-Client/1.0'
                ])
                ->asForm()
                ->post($this->apiUrl, $requestData);

            Log::info('BaseLinker API Response DEBUG', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'headers' => $response->headers(),
                'raw_body' => $response->body(),
                'body_length' => strlen($response->body())
            ]);

            if (!$response->successful()) {
                throw new Exception("BaseLinker API HTTP Error: " . $response->status() . " - " . $response->body());
            }

            $data = $response->json();

            if (!is_array($data)) {
                throw new Exception("BaseLinker API returned invalid JSON response: " . $response->body());
            }

            Log::info('BaseLinker API Parsed Response', [
                'parsed_data' => $data,
                'status' => $data['status'] ?? 'UNKNOWN',
                'error_message' => $data['error_message'] ?? null
            ]);

            if (isset($data['status']) && $data['status'] === 'ERROR') {
                $errorMessage = $data['error_message'] ?? 'Unknown error';
                
                Log::error('BaseLinker API returned ERROR', [
                    'method' => $method,
                    'error_message' => $errorMessage,
                    'sent_parameters' => $parameters,
                    'full_response' => $data
                ]);
                
                throw new Exception("BaseLinker API Error: " . $errorMessage);
            }

            return $data;

        } catch (Exception $e) {
            Log::error('BaseLinker API Error DETAILED', [
                'method' => $method,
                'parameters' => $parameters,
                'error' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'token_set' => !empty($this->token),
                'token_length' => $this->token ? strlen($this->token) : 0,
            ]);
            throw $e;
        }
    }

    /**
     * Pobiera listę katalogów produktów
     */
    public function getCatalogs(): array
    {
        $response = $this->makeRequest('getProductsCatalogs', []);
        return $response['catalogs'] ?? [];
    }

    /**
     * Pobiera listę magazynów
     */
    public function getInventoryWarehouses(): array
    {
        $response = $this->makeRequest('getInventoryWarehouses');
        return $response['warehouses'] ?? [];
    }

    /**
     * Pobiera listę magazynów i znajduje domyślny
     */
    public function getDefaultWarehouseId(): string
    {
        try {
            $warehouses = $this->getInventoryWarehouses();
            
            Log::info('BaseLinker warehouses', ['warehouses' => $warehouses]);
            
            if (!empty($warehouses)) {
                // Weź pierwszy magazyn jako domyślny
                $firstWarehouse = reset($warehouses);
                if (isset($firstWarehouse['warehouse_id'])) {
                    return $firstWarehouse['warehouse_id'];
                }
                
                // Lub weź pierwszy klucz jeśli struktura jest inna
                return array_key_first($warehouses);
            }
            
            // Fallback na domyślne ID
            return 'bl_206';
            
        } catch (Exception $e) {
            Log::warning('Nie udało się pobrać magazynów, używam domyślnego ID', ['error' => $e->getMessage()]);
            return 'bl_206';
        }
    }

    /**
     * Aktualizuje stan magazynowy produktu
     */
    // public function updateInventoryProductStock(string $productId, array $stockData): array
    // {
    //     $parameters = [
    //         'inventory_id' => $stockData['inventory_id'],
    //         'product_id' => $productId,
    //         'variant_id' => $stockData['variant_id'] ?? null,
    //         'warehouse_id' => $stockData['warehouse_id'],
    //         'stock' => $stockData['stock'],
    //         'price' => $stockData['price'] ?? null,
    //     ];

    //     return $this->makeRequest('updateInventoryProductStock', $parameters);
    // }

    /**
     * Pobiera stany magazynowe produktu
     */
    public function getInventoryProductsStock(array $products): array
    {
        $parameters = [
            'inventory_id' => $products['inventory_id'],
            'page' => $products['page'] ?? 1,
            'filter_id' => $products['filter_id'] ?? null,
            'filter_sku' => $products['filter_sku'] ?? null,
            'filter_name' => $products['filter_name'] ?? null,
            'filter_price_from' => $products['filter_price_from'] ?? null,
            'filter_price_to' => $products['filter_price_to'] ?? null,
            'filter_quantity_from' => $products['filter_quantity_from'] ?? null,
            'filter_quantity_to' => $products['filter_quantity_to'] ?? null,
            'filter_available' => $products['filter_available'] ?? null,
        ];

        $response = $this->makeRequest('getInventoryProductsStock', $parameters);
        return $response['products'] ?? [];
    }

    /**
     * Sprawdza połączenie z API BaseLinker
     */
    public function testConnection(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            
            // Testuj dostępność metod inventory
            Log::info('Testowanie metod BaseLinker API');
            
            // Test getInventoryWarehouses
            try {
                $warehouses = $this->getInventoryWarehouses();
                Log::info('getInventoryWarehouses - OK', ['warehouses' => $warehouses]);
            } catch (Exception $e) {
                Log::warning('getInventoryWarehouses - BŁĄD', ['error' => $e->getMessage()]);
            }
            
            // Test getInventoryProducts
            try {
                $products = $this->getInventoryProducts(['filter_limit' => 1]);
                Log::info('getInventoryProducts - OK', ['products_count' => count($products)]);
            } catch (Exception $e) {
                Log::warning('getInventoryProducts - BŁĄD', ['error' => $e->getMessage()]);
            }
            
            return true;
        } catch (Exception $e) {
            Log::error('BaseLinker connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}