<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaseLinkerService;

class BaseLinkerSetup extends Command
{
    protected $signature = 'baselinker:setup';
    protected $description = 'Sprawdza i konfiguruje połączenie z BaseLinker';

    public function handle()
    {
        $this->info('🔧 Sprawdzanie konfiguracji BaseLinker...');
        
        // Sprawdź token
        $token = config('baselinker.token');
        if (empty($token)) {
            $this->error('❌ BaseLinker API token nie jest skonfigurowany');
            $this->warn('Dodaj BASELINKER_API_TOKEN do pliku .env');
            $this->line('Przykład:');
            $this->line('BASELINKER_API_TOKEN=your_token_here');
            return 1;
        }
        
        $this->info('✅ Token API jest ustawiony');
        
        // Test połączenia
        $this->info('🌐 Testowanie połączenia z BaseLinker...');
        
        try {
            $service = app(BaseLinkerService::class);
            
            if (!$service->isConfigured()) {
                $this->error('❌ Serwis BaseLinker nie jest skonfigurowany');
                return 1;
            }
            
            if ($service->testConnection()) {
                $this->info('✅ Połączenie z BaseLinker działa poprawnie');
                
                // Pokaż dostępne katalogi
                // try {
                //     $catalogs = $service->getCatalogs();
                //     $this->info('📁 Dostępne katalogi produktów:');
                //     foreach ($catalogs as $catalog) {
                //         $this->line("  - {$catalog['catalog_id']}: {$catalog['name']}");
                //     }
                // } catch (\Exception $e) {
                //     $this->warn('⚠️  Nie udało się pobrać katalogów: ' . $e->getMessage());
                // }
                
                // Pokaż magazyny
                try {
                    $warehouses = $service->getInventoryWarehouses();
                    $this->info('🏪 Dostępne magazyny:');
                    foreach ($warehouses as $warehouse) {
                        $this->line("  - {$warehouse['warehouse_id']}: {$warehouse['name']}");
                    }
                } catch (\Exception $e) {
                    $this->warn('⚠️  Nie udało się pobrać magazynów: ' . $e->getMessage());
                }
                
            } else {
                $this->error('❌ Połączenie z BaseLinker nie działa');
                $this->warn('Sprawdź token API i połączenie internetowe');
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Błąd podczas testowania: ' . $e->getMessage());
            return 1;
        }
        
        // Sprawdź konfigurację
        $this->info('⚙️  Sprawdzanie konfiguracji...');
        
        $config = config('baselinker');
        $this->table(['Ustawienie', 'Wartość'], [
            // ['Default Catalog ID', $config['default_catalog_id'] ?? 'nie ustawione'],
            ['Default Inventory ID', $config['default_inventory_id'] ?? 'nie ustawione'],
            ['Default Warehouse ID', $config['default_warehouse_id'] ?? 'nie ustawione'],
            ['Sync Enabled', $config['sync_enabled'] ? 'tak' : 'nie'],
            ['Batch Size', $config['batch_size'] ?? 'nie ustawione'],
        ]);
        
        $this->info('🎉 Konfiguracja BaseLinker została sprawdzona!');
        
        // Pokaż dostępne komendy
        $this->info('📝 Dostępne komendy:');
        $this->line('  php artisan baselinker:sync --products     # Synchronizacja produktów');
        $this->line('  php artisan baselinker:sync --stock        # Synchronizacja stanów');
        $this->line('  php artisan baselinker:sync-orders --days=7 # Synchronizacja zamówień');
        
        return 0;
    }
}