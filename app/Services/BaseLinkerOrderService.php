<?php
namespace App\Services;

use App\Models\BaseLinkerOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Exception;

class BaseLinkerOrderService extends BaseLinkerService
{
    /**
     * Pobiera zamówienia z BaseLinker
     */
    public function getOrders(array $filters = []): array
    {
        $parameters = [
            'date_confirmed_from' => $filters['date_confirmed_from'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'id_from' => $filters['id_from'] ?? null,
            'id_to' => $filters['id_to'] ?? null,
            'get_unconfirmed_orders' => $filters['get_unconfirmed_orders'] ?? true,
            'status_id' => $filters['status_id'] ?? null,
            'filter_email' => $filters['filter_email'] ?? null,
        ];

        // Usuń null values
        $parameters = array_filter($parameters, fn($value) => !is_null($value));

        $response = $this->makeRequest('getOrders', $parameters);
        return $response['orders'] ?? [];
    }

    /**
     * Synchronizuje zamówienia z BaseLinker do lokalnej bazy danych
     */
    public function syncOrders(array $filters = []): array
    {
        try {
            $orders = $this->getOrders($filters);
            $syncedCount = 0;
            $errors = [];

            foreach ($orders as $orderData) {
                try {
                    BaseLinkerOrder::createOrUpdateFromBaseLinker($orderData);
                    $syncedCount++;
                } catch (Exception $e) {
                    $errors[] = [
                        'order_id' => $orderData['order_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    Log::error('Failed to sync BaseLinker order', [
                        'order_id' => $orderData['order_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('BaseLinker orders sync completed', [
                'total_orders' => count($orders),
                'synced_count' => $syncedCount,
                'errors_count' => count($errors)
            ]);

            return [
                'success' => true,
                'total_orders' => count($orders),
                'synced_count' => $syncedCount,
                'errors_count' => count($errors),
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error('BaseLinker orders sync failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Pobiera statusy zamówień z BaseLinker
     */
    public function getOrderStatusList(): array
    {
        $response = $this->makeRequest('getOrderStatusList');
        return $response['statuses'] ?? [];
    }

    /**
     * Pobiera źródła zamówień z BaseLinker
     */
    public function getOrderSources(): array
    {
        $response = $this->makeRequest('getOrderSources');
        return $response['sources'] ?? [];
    }

    /**
     * Synchronizuje zamówienia z ostatnich X dni
     */
    public function syncRecentOrders(int $days = 7): array
    {
        $dateFrom = now()->subDays($days)->timestamp;
        
        return $this->syncOrders([
            'date_from' => $dateFrom,
            'get_unconfirmed_orders' => true
        ]);
    }

    /**
     * Synchronizuje tylko niepotwierdzone zamówienia
     */
    public function syncUnconfirmedOrders(): array
    {
        return $this->syncOrders([
            'get_unconfirmed_orders' => true
        ]);
    }
}