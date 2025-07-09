{{-- resources/views/filament/modals/baselinker-data-preview.blade.php --}}
<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                Parametry BaseLinker {{ isset($isInventory) && $isInventory ? 'Inventory' : 'Catalog' }}
            </h3>
            
            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">inventory_id:</span>
                    <span class="font-mono text-sm bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded">
                        {{ $blData['inventory_id'] }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">sku:</span>
                    <span class="font-mono text-sm 
                        @if(empty($blData['sku']) || $blData['sku'] === 'BRAK_SKU') 
                            bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded">
                        {{ $blData['sku'] }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">ean:</span>
                    <span class="font-mono text-sm 
                        @if(empty($blData['ean'])) 
                            bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded">
                        {{ $blData['ean'] ?: 'Brak' }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">price:</span>
                    <span class="font-mono text-sm 
                        @if($blData['price'] <= 0) 
                            bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded">
                        {{ number_format($blData['price'], 2, ',', ' ') }} PLN
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">quantity:</span>
                    <span class="font-mono text-sm 
                        @if($blData['quantity'] < 0) 
                            bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                        @elseif($blData['quantity'] == 0) 
                            bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded">
                        {{ $blData['quantity'] }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">text_fields.name:</span>
                    <span class="font-mono text-sm 
                        @if(empty($blData['text_fields']['name']) || $blData['text_fields']['name'] === 'BRAK_NAZWY') 
                            bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded max-w-xs truncate">
                        {{ Str::limit($blData['text_fields']['name'], 30) }}
                    </span>
                </div>
            </div>
        </div>
        
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                Status gotowości
            </h3>
            
            <div class="space-y-3">
                @php
                    $isReady = !empty($blData['sku']) && 
                              $blData['sku'] !== 'BRAK_SKU' &&
                              !empty($blData['text_fields']['name']) && 
                              $blData['text_fields']['name'] !== 'BRAK_NAZWY' &&
                              $blData['price'] > 0;
                @endphp
                
                <div class="p-4 rounded-lg border-2 
                    @if($isReady) 
                        border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20
                    @else 
                        border-red-200 bg-red-50 dark:border-red-700 dark:bg-red-900/20
                    @endif">
                    
                    <div class="flex items-center space-x-2">
                        @if($isReady)
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="font-semibold text-green-800 dark:text-green-200">Gotowy do wysłania</span>
                        @else
                            <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="font-semibold text-red-800 dark:text-red-200">Brakuje danych</span>
                        @endif
                    </div>
                    
                    @if(!$isReady)
                        <div class="mt-3 text-sm text-red-700 dark:text-red-300">
                            <p class="font-medium mb-2">Wymagane pola do uzupełnienia:</p>
                            <ul class="space-y-1">
                                @if(empty($blData['sku']) || $blData['sku'] === 'BRAK_SKU')
                                    <li>• SKU (kod produktu)</li>
                                @endif
                                @if(empty($blData['text_fields']['name']) || $blData['text_fields']['name'] === 'BRAK_NAZWY')
                                    <li>• Nazwa produktu</li>
                                @endif
                                @if($blData['price'] <= 0)
                                    <li>• Cena sprzedaży (> 0)</li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>
                
                @if($product->baselinker_id)
                    <div class="p-4 rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-700 dark:bg-blue-900/20">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="font-semibold text-blue-800 dark:text-blue-200">Już w BaseLinker Inventory</span>
                        </div>
                        <p class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                            ID BaseLinker: <span class="font-mono">{{ $product->baselinker_id }}</span>
                        </p>
                        @if($product->last_baselinker_sync)
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Ostatnia synchronizacja: {{ $product->last_baselinker_sync->format('d.m.Y H:i') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>{{-- resources/views/filament/modals/baselinker-data-preview.blade.php --}}
<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                Parametry BaseLinker
            </h3>
            
            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">inventory_id:</span>
                    <span class="font-mono text-sm bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded">
                        {{ $blData['inventory_id'] }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">sku:</span>
                    <span class="font-mono text-sm 
                        @if(empty($blData['sku']) || $blData['sku'] === 'BRAK_SKU') 
                            bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded">
                        {{ $blData['sku'] }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">ean:</span>
                    <span class="font-mono text-sm 
                        @if(empty($blData['ean'])) 
                            bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded">
                        {{ $blData['ean'] ?: 'Brak' }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">price:</span>
                    <span class="font-mono text-sm 
                        @if($blData['price'] <= 0) 
                            bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded">
                        {{ number_format($blData['price'], 2, ',', ' ') }} PLN
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">quantity:</span>
                    <span class="font-mono text-sm 
                        @if($blData['quantity'] < 0) 
                            bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                        @elseif($blData['quantity'] == 0) 
                            bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded">
                        {{ $blData['quantity'] }}
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="font-medium text-gray-700 dark:text-gray-300">text_fields.name:</span>
                    <span class="font-mono text-sm 
                        @if(empty($blData['text_fields']['name']) || $blData['text_fields']['name'] === 'BRAK_NAZWY') 
                            bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                        @else 
                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @endif 
                        px-2 py-1 rounded max-w-xs truncate">
                        {{ Str::limit($blData['text_fields']['name'], 30) }}
                    </span>
                </div>
            </div>
        </div>
        
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                Status gotowości
            </h3>
            
            <div class="space-y-3">
                @php
                    $isReady = !empty($blData['sku']) && 
                              $blData['sku'] !== 'BRAK_SKU' &&
                              !empty($blData['text_fields']['name']) && 
                              $blData['text_fields']['name'] !== 'BRAK_NAZWY' &&
                              $blData['price'] > 0;
                @endphp
                
                <div class="p-4 rounded-lg border-2 
                    @if($isReady) 
                        border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/20
                    @else 
                        border-red-200 bg-red-50 dark:border-red-700 dark:bg-red-900/20
                    @endif">
                    
                    <div class="flex items-center space-x-2">
                        @if($isReady)
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="font-semibold text-green-800 dark:text-green-200">Gotowy do wysłania</span>
                        @else
                            <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="font-semibold text-red-800 dark:text-red-200">Brakuje danych</span>
                        @endif
                    </div>
                    
                    @if(!$isReady)
                        <div class="mt-3 text-sm text-red-700 dark:text-red-300">
                            <p class="font-medium mb-2">Wymagane pola do uzupełnienia:</p>
                            <ul class="space-y-1">
                                @if(empty($blData['sku']) || $blData['sku'] === 'BRAK_SKU')
                                    <li>• SKU (kod produktu)</li>
                                @endif
                                @if(empty($blData['text_fields']['name']) || $blData['text_fields']['name'] === 'BRAK_NAZWY')
                                    <li>• Nazwa produktu</li>
                                @endif
                                @if($blData['price'] <= 0)
                                    <li>• Cena sprzedaży (> 0)</li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>
                
                @if($product->baselinker_id)
                    <div class="p-4 rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-700 dark:bg-blue-900/20">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="font-semibold text-blue-800 dark:text-blue-200">Już w BaseLinker</span>
                        </div>
                        <p class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                            ID BaseLinker: <span class="font-mono">{{ $product->baselinker_id }}</span>
                        </p>
                        @if($product->last_baselinker_sync)
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Ostatnia synchronizacja: {{ $product->last_baselinker_sync->format('d.m.Y H:i') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <div class="mt-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
            JSON do wysłania (Inventory API)
        </h3>
        
        <div class="relative">
            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg text-sm overflow-x-auto"><code>{{ $jsonData }}</code></pre>
            <button 
                type="button"
                onclick="navigator.clipboard.writeText({{ json_encode($jsonData) }})"
                class="absolute top-2 right-2 p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                title="Kopiuj JSON">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"></path>
                    <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"></path>
                </svg>
            </button>
        </div>
    </div>
    
    <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
        <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">
            🔄 BaseLinker Inventory API
        </h4>
        <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
            <p><strong>inventory_id ({{ $blData['inventory_id'] }}):</strong> ID magazynu BaseLinker</p>
            <p><strong>sku:</strong> Unikalny kod produktu (wymagany)</p>
            <p><strong>ean:</strong> Kod kreskowy EAN (opcjonalny)</p>
            <p><strong>price:</strong> Cena brutto w PLN (wymagana > 0)</p>
            <p><strong>quantity:</strong> Ilość na magazynie (wymagana)</p>
            <p><strong>text_fields.name:</strong> Nazwa produktu (wymagana)</p>
        </div>
    </div>
    
    <div class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
        <h4 class="font-medium text-green-900 dark:text-green-100 mb-2">
            ✅ Testowane w Postman
        </h4>
        <div class="text-sm text-green-800 dark:text-green-200">
            <p>Ten format JSON został przetestowany i działa w Postman z BaseLinker API.</p>
            <p><strong>Endpoint:</strong> POST https://api.baselinker.com/connector.php</p>
            <p><strong>Metoda:</strong> addInventoryProduct</p>
        </div>
    </div>
</div>