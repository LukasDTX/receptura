<?php
namespace App\Filament\Resources\BaseLinkerProductResource\Pages;

use App\Filament\Resources\BaseLinkerProductResource;
use App\Services\BaseLinkerService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ListBaseLinkerProducts extends ListRecords
{
    protected static string $resource = BaseLinkerProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\Action::make('sync_all_stocks')
            //     ->label('Pobierz stany z BaseLinker')
            //     ->icon('heroicon-o-arrow-down-circle')
            //     ->color('primary')
            //     ->requiresConfirmation()
            //     ->modalHeading('Pobieranie stanów z BaseLinker')
            //     ->modalDescription('Pobierze aktualne stany wszystkich produktów z BaseLinker.')
            //     ->action(function () {
            //         $service = app(BaseLinkerService::class);
            //         $products = $this->getModel()::whereNotNull('baselinker_id')
            //             ->where('baselinker_id', '!=', '')
            //             ->where('sync_with_baselinker', true)
            //             ->get();
                    
            //         $updatedCount = 0;
                    
            //         foreach ($products as $product) {
            //             try {
            //                 $stockData = $service->getInventoryProductsStock([
            //                     'inventory_id' => config('baselinker.default_inventory_id'),
            //                     'filter_id' => $product->baselinker_id
            //                 ]);
                            
            //                 if (!empty($stockData)) {
            //                     $product->update([
            //                         'baselinker_stock' => $stockData[0]['stock'] ?? 0,
            //                         'baselinker_price' => $stockData[0]['price_brutto'] ?? null,
            //                         'last_baselinker_sync' => now()
            //                     ]);
            //                     $updatedCount++;
            //                 }
            //             } catch (\Exception $e) {
            //                 // Continue with other products
            //             }
            //         }
                    
            //         Notification::make()
            //             ->title('Stany pobrane')
            //             ->body("Zaktualizowano {$updatedCount} produktów")
            //             ->success()
            //             ->send();
            //     }),

            // Actions\Action::make('push_all_stocks')
            //     ->label('Wyślij stany do BaseLinker')
            //     ->icon('heroicon-o-arrow-up-circle')
            //     ->color('success')
            //     ->requiresConfirmation()
            //     ->modalHeading('Wysyłanie stanów do BaseLinker')
            //     ->modalDescription('Wyśle lokalne stany magazynowe do BaseLinker.')
            //     ->action(function () {
            //         $service = app(BaseLinkerService::class);
            //         $products = $this->getModel()::whereNotNull('baselinker_id')
            //             ->where('baselinker_id', '!=', '')
            //             ->where('sync_with_baselinker', true)
            //             ->get();
                    
            //         $updatedCount = 0;
                    
            //         foreach ($products as $product) {
            //             try {
            //                 $stockData = [
            //                     'inventory_id' => config('baselinker.default_inventory_id'),
            //                     'warehouse_id' => config('baselinker.default_warehouse_id'),
            //                     'stock' => $product->stan_magazynowy ?? 0,
            //                     'price' => $product->cena_sprzedazy,
            //                 ];
                            
            //                 $service->updateInventoryProductStock($product->baselinker_id, $stockData);
            //                 $product->update(['last_baselinker_sync' => now()]);
            //                 $updatedCount++;
                            
            //             } catch (\Exception $e) {
            //                 // Continue with other products
            //             }
            //         }
                    
            //         Notification::make()
            //             ->title('Stany wysłane')
            //             ->body("Wysłano {$updatedCount} produktów do BaseLinker")
            //             ->success()
            //             ->send();
            //     }),

            // Actions\Action::make('check_differences')
            //     ->label('Sprawdź różnice')
            //     ->icon('heroicon-o-clipboard-document-list')
            //     ->color('warning')
            //     ->action(function () {
            //         $products = $this->getModel()::whereNotNull('baselinker_id')
            //             ->where('baselinker_id', '!=', '')
            //             ->whereNotNull('baselinker_stock')
            //             ->get();
                    
            //         $differences = [];
            //         $totalDifference = 0;
                    
            //         foreach ($products as $product) {
            //             $localStock = $product->stan_magazynowy ?? 0;
            //             $blStock = $product->baselinker_stock ?? 0;
                        
            //             if ($localStock != $blStock) {
            //                 $diff = $localStock - $blStock;
            //                 $differences[] = [
            //                     'nazwa' => $product->nazwa,
            //                     'lokalny' => $localStock,
            //                     'baselinker' => $blStock,
            //                     'roznica' => $diff
            //                 ];
            //                 $totalDifference += abs($diff);
            //             }
            //         }
                    
            //         $message = count($differences) . ' produktów ma różne stany';
            //         if (count($differences) > 0) {
            //             $maxDiff = max(array_map('abs', array_column($differences, 'roznica')));
            //             $message .= ". Największa różnica: {$maxDiff}. Łączna różnica: {$totalDifference}";
            //         }
                    
            //         Notification::make()
            //             ->title('Sprawdzono różnice')
            //             ->body($message)
            //             ->info()
            //             ->send();
            //     }),

            //     Actions\Action::make('calculate_costs')
            //         ->label('Przelicz koszty')
            //         ->icon('heroicon-o-calculator')
            //         ->color('info')
            //         ->requiresConfirmation()
            //         ->modalHeading('Przeliczanie kosztów')
            //         ->modalDescription('Przelicza koszty całkowite dla wszystkich produktów.')
            //         ->action(function () {
            //             $products = $this->getModel()::whereHas('receptura')->get();
            //             $updatedCount = 0;
                        
            //             foreach ($products as $product) {
            //                 try {
            //                     $product->obliczKosztCalkowity();
            //                     $updatedCount++;
            //                 } catch (\Exception $e) {
            //                     // Continue with other products
            //                 }
            //             }
                        
            //             Notification::make()
            //                 ->title('Koszty przeliczone')
            //                 ->body("Zaktualizowano {$updatedCount} produktów")
            //                 ->success()
            //                 ->send();
            //     }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Wszystkie')
                ->badge(fn () => $this->getModel()::count()),
            
            'with_baselinker' => Tab::make('Z BaseLinker ID')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('baselinker_id')->where('baselinker_id', '!=', ''))
                ->badge(fn () => $this->getModel()::whereNotNull('baselinker_id')->where('baselinker_id', '!=', '')->count())
                ->badgeColor('success'),
            
            'without_baselinker' => Tab::make('Bez BaseLinker ID')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function($q) {
                    $q->whereNull('baselinker_id')->orWhere('baselinker_id', '');
                }))
                ->badge(fn () => $this->getModel()::where(function($q) {
                    $q->whereNull('baselinker_id')->orWhere('baselinker_id', '');
                })->count())
                ->badgeColor('warning'),
            
            'sync_enabled' => Tab::make('Sync włączony')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('sync_with_baselinker', true))
                ->badge(fn () => $this->getModel()::where('sync_with_baselinker', true)->count())
                ->badgeColor('info'),
            
            'sync_disabled' => Tab::make('Sync wyłączony')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('sync_with_baselinker', false))
                ->badge(fn () => $this->getModel()::where('sync_with_baselinker', false)->count())
                ->badgeColor('gray'),
            
            'low_stock' => Tab::make('Niski stan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('stan_magazynowy', '<=', 5))
                ->badge(fn () => $this->getModel()::where('stan_magazynowy', '<=', 5)->count())
                ->badgeColor('warning'),
            
            'out_of_stock' => Tab::make('Brak w magazynie')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('stan_magazynowy', '<=', 0))
                ->badge(fn () => $this->getModel()::where('stan_magazynowy', '<=', 0)->count())
                ->badgeColor('danger'),
            
            'stock_differences' => Tab::make('Różnice w stanach')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('baselinker_id')
                    ->where('baselinker_id', '!=', '')
                    ->whereNotNull('baselinker_stock')
                    ->whereRaw('stan_magazynowy != baselinker_stock'))
                ->badge(fn () => $this->getModel()::whereNotNull('baselinker_id')
                    ->where('baselinker_id', '!=', '')
                    ->whereNotNull('baselinker_stock')
                    ->whereRaw('stan_magazynowy != baselinker_stock')->count())
                ->badgeColor('warning'),
        ];
    }
}