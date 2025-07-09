<?php
// database/seeders/BaseLinkerOrderSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BaseLinkerOrder;
use Carbon\Carbon;

class BaseLinkerOrderSeeder extends Seeder
{
    public function run(): void
    {
        // Przykładowe dane zamówień BaseLinker do testów
        $sampleOrders = [
            [
                'baselinker_order_id' => 16147356,
                'external_order_id' => '',
                'order_source' => 'personal',
                'confirmed' => true,
                'date_add' => now()->subDays(2),
                'date_confirmed' => now()->subDays(2)->addHours(1),
                'email' => 'jan.kowalski@example.com',
                'phone' => '+48123456789',
                'delivery_fullname' => 'Jan Kowalski',
                'delivery_address' => 'ul. Przykładowa 123',
                'delivery_city' => 'Warszawa',
                'delivery_postcode' => '00-001',
                'delivery_country' => 'Polska',
                'delivery_method' => 'InPost Paczkomaty',
                'delivery_package_nr' => 'INP123456789PL',
                'payment_done' => true,
                'currency' => 'PLN',
                'products' => collect([
                    [
                        'name' => 'Immunity shot',
                        'sku' => '01-1-1-1-1-1-1-1-1-1-1-1-1-1-2-1-1-1-1-1-1-2-1-1-1',
                        'price_brutto' => 20.00,
                        'quantity' => 5,
                        'tax_rate' => 23,
                    ]
                ]),
                'synced_at' => now(),
            ],
            [
                'baselinker_order_id' => 16147357,
                'external_order_id' => 'AL-123456',
                'order_source' => 'allegro',
                'confirmed' => false,
                'date_add' => now()->subHours(4),
                'email' => 'anna.nowak@example.com',
                'phone' => '+48987654321',
                'delivery_fullname' => 'Anna Nowak',
                'delivery_address' => 'ul. Testowa 456',
                'delivery_city' => 'Kraków',
                'delivery_postcode' => '30-001',
                'delivery_country' => 'Polska',
                'delivery_method' => 'Kurier DPD',
                'payment_done' => false,
                'currency' => 'PLN',
                'user_comments' => 'Proszę o szybką realizację',
                'products' => collect([
                    [
                        'name' => 'Produkt testowy 1',
                        'sku' => 'TEST-001',
                        'price_brutto' => 49.99,
                        'quantity' => 2,
                        'tax_rate' => 23,
                    ],
                    [
                        'name' => 'Produkt testowy 2',
                        'sku' => 'TEST-002',
                        'price_brutto' => 29.99,
                        'quantity' => 1,
                        'tax_rate' => 23,
                    ]
                ]),
                'synced_at' => now(),
            ],
        ];

        foreach ($sampleOrders as $orderData) {
            BaseLinkerOrder::create($orderData);
        }

        // Wygeneruj dodatkowe losowe zamówienia
        for ($i = 0; $i < 20; $i++) {
            BaseLinkerOrder::create([
                'baselinker_order_id' => 16147000 + $i,
                'external_order_id' => rand(0, 1) ? 'EXT-' . rand(100000, 999999) : '',
                'order_source' => $this->getRandomSource(),
                'confirmed' => rand(0, 1),
                'date_add' => now()->subDays(rand(0, 30)),
                'date_confirmed' => rand(0, 1) ? now()->subDays(rand(0, 30)) : null,
                'email' => 'customer' . $i . '@example.com',
                'phone' => '+4812345' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'delivery_fullname' => 'Klient ' . $i,
                'delivery_address' => 'ul. Testowa ' . ($i + 1),
                'delivery_city' => $this->getRandomCity(),
                'delivery_postcode' => rand(10, 99) . '-' . rand(100, 999),
                'delivery_country' => 'Polska',
                'delivery_method' => $this->getRandomDeliveryMethod(),
                'delivery_package_nr' => rand(0, 1) ? $this->generateTrackingNumber() : '',
                'payment_done' => rand(0, 1),
                'currency' => 'PLN',
                'user_comments' => rand(0, 1) ? 'Przykładowy komentarz klienta' : '',
                'products' => collect($this->generateRandomProducts()),
                'synced_at' => now(),
            ]);
        }
    }

    private function getRandomSource(): string
    {
        $sources = ['personal', 'shop', 'allegro', 'amazon', 'ebay'];
        return $sources[array_rand($sources)];
    }

    private function getRandomCity(): string
    {
        $cities = ['Warszawa', 'Kraków', 'Gdańsk', 'Wrocław', 'Poznań', 'Łódź', 'Katowice'];
        return $cities[array_rand($cities)];
    }

    private function getRandomDeliveryMethod(): string
    {
        $methods = ['InPost Paczkomaty', 'Kurier DPD', 'Poczta Polska', 'UPS', 'Odbiór osobisty'];
        return $methods[array_rand($methods)];
    }

    private function generateTrackingNumber(): string
    {
        $prefixes = ['INP', 'DPD', 'UPS', 'PP'];
        $prefix = $prefixes[array_rand($prefixes)];
        return $prefix . rand(100000000, 999999999) . 'PL';
    }

    private function generateRandomProducts(): array
    {
        $products = [];
        $productCount = rand(1, 4);
        
        for ($i = 0; $i < $productCount; $i++) {
            $products[] = [
                'name' => 'Produkt ' . ($i + 1),
                'sku' => 'SKU-' . rand(1000, 9999) . '-' . $i,
                'price_brutto' => rand(10, 200) + (rand(0, 99) / 100),
                'quantity' => rand(1, 5),
                'tax_rate' => 23,
            ];
        }
        
        return $products;
    }
}
