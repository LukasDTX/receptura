<?php

// app/Models/BaseLinkerOrder.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BaseLinkerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'baselinker_order_id',
        'shop_order_id',
        'external_order_id',
        'order_source',
        'order_source_id',
        'order_source_info',
        'order_status_id',
        'confirmed',
        'date_confirmed',
        'date_add',
        'date_in_status',
        'user_login',
        'phone',
        'email',
        'user_comments',
        'admin_comments',
        'currency',
        'payment_method',
        'payment_method_cod',
        'payment_done',
        'delivery_method',
        'delivery_price',
        'delivery_package_module',
        'delivery_package_nr',
        'delivery_fullname',
        'delivery_company',
        'delivery_address',
        'delivery_city',
        'delivery_state',
        'delivery_postcode',
        'delivery_country_code',
        'delivery_point_id',
        'delivery_point_name',
        'delivery_point_address',
        'delivery_point_postcode',
        'delivery_point_city',
        'invoice_fullname',
        'invoice_company',
        'invoice_nip',
        'invoice_address',
        'invoice_city',
        'invoice_state',
        'invoice_postcode',
        'invoice_country_code',
        'want_invoice',
        'extra_field_1',
        'extra_field_2',
        'order_page',
        'pick_state',
        'pack_state',
        'delivery_country',
        'invoice_country',
        'products',
        'synced_at',
    ];

    protected $casts = [
        'confirmed' => 'boolean',
        'payment_done' => 'boolean',
        'want_invoice' => 'boolean',
        'date_confirmed' => 'datetime',
        'date_add' => 'datetime',
        'date_in_status' => 'datetime',
        'synced_at' => 'datetime',
        'delivery_price' => 'decimal:2',
        'products' => 'collection',
        'baselinker_order_id' => 'integer',
        'shop_order_id' => 'integer',
        'order_source_id' => 'integer',
        'order_status_id' => 'integer',
        'pick_state' => 'integer',
        'pack_state' => 'integer',
    ];

    protected $attributes = [
        'currency' => 'PLN',
    ];

    /**
     * Unikalne ID dla identyfikacji
     */
    public function getRouteKeyName(): string
    {
        return 'baselinker_order_id';
    }

    /**
     * Scopes
     */
    public function scopeConfirmed($query)
    {
        return $query->where('confirmed', true);
    }

    public function scopeUnconfirmed($query)
    {
        return $query->where('confirmed', false);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_done', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_done', false);
    }

    public function scopeFromSource($query, string $source)
    {
        return $query->where('order_source', $source);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('date_add', '>', now()->subDays($days));
    }

    /**
     * Accessors
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->products->sum(function ($product) {
            return $product['price_brutto'] * $product['quantity'];
        });
    }

    public function getProductsCountAttribute(): int
    {
        return $this->products->count();
    }

    public function getFullDeliveryAddressAttribute(): string
    {
        return collect([
            $this->delivery_address,
            $this->delivery_postcode . ' ' . $this->delivery_city,
            $this->delivery_state,
            $this->delivery_country,
        ])->filter()->implode(', ');
    }

    public function getFullInvoiceAddressAttribute(): string
    {
        return collect([
            $this->invoice_address,
            $this->invoice_postcode . ' ' . $this->invoice_city,
            $this->invoice_state,
            $this->invoice_country,
        ])->filter()->implode(', ');
    }

    /**
     * Statyczna metoda do tworzenia/aktualizacji z danych BaseLinker
     */
    public static function createOrUpdateFromBaseLinker(array $orderData): self
    {
        $order = static::updateOrCreate(
            ['baselinker_order_id' => $orderData['order_id']],
            [
                'shop_order_id' => $orderData['shop_order_id'] ?? 0,
                'external_order_id' => $orderData['external_order_id'] ?? '',
                'order_source' => $orderData['order_source'] ?? '',
                'order_source_id' => $orderData['order_source_id'] ?? 0,
                'order_source_info' => $orderData['order_source_info'] ?? '',
                'order_status_id' => $orderData['order_status_id'] ?? 0,
                'confirmed' => $orderData['confirmed'] ?? false,
                'date_confirmed' => isset($orderData['date_confirmed']) && $orderData['date_confirmed'] > 0 
                    ? Carbon::createFromTimestamp($orderData['date_confirmed']) 
                    : null,
                'date_add' => isset($orderData['date_add']) && $orderData['date_add'] > 0 
                    ? Carbon::createFromTimestamp($orderData['date_add']) 
                    : now(),
                'date_in_status' => isset($orderData['date_in_status']) && $orderData['date_in_status'] > 0 
                    ? Carbon::createFromTimestamp($orderData['date_in_status']) 
                    : null,
                'user_login' => $orderData['user_login'] ?? '',
                'phone' => $orderData['phone'] ?? '',
                'email' => $orderData['email'] ?? '',
                'user_comments' => $orderData['user_comments'] ?? '',
                'admin_comments' => $orderData['admin_comments'] ?? '',
                'currency' => $orderData['currency'] ?? 'PLN',
                'payment_method' => $orderData['payment_method'] ?? '',
                'payment_method_cod' => $orderData['payment_method_cod'] ?? '',
                'payment_done' => $orderData['payment_done'] ?? false,
                'delivery_method' => $orderData['delivery_method'] ?? '',
                'delivery_price' => $orderData['delivery_price'] ?? 0,
                'delivery_package_module' => $orderData['delivery_package_module'] ?? '',
                'delivery_package_nr' => $orderData['delivery_package_nr'] ?? '',
                'delivery_fullname' => $orderData['delivery_fullname'] ?? '',
                'delivery_company' => $orderData['delivery_company'] ?? '',
                'delivery_address' => $orderData['delivery_address'] ?? '',
                'delivery_city' => $orderData['delivery_city'] ?? '',
                'delivery_state' => $orderData['delivery_state'] ?? '',
                'delivery_postcode' => $orderData['delivery_postcode'] ?? '',
                'delivery_country_code' => $orderData['delivery_country_code'] ?? '',
                'delivery_point_id' => $orderData['delivery_point_id'] ?? '',
                'delivery_point_name' => $orderData['delivery_point_name'] ?? '',
                'delivery_point_address' => $orderData['delivery_point_address'] ?? '',
                'delivery_point_postcode' => $orderData['delivery_point_postcode'] ?? '',
                'delivery_point_city' => $orderData['delivery_point_city'] ?? '',
                'invoice_fullname' => $orderData['invoice_fullname'] ?? '',
                'invoice_company' => $orderData['invoice_company'] ?? '',
                'invoice_nip' => $orderData['invoice_nip'] ?? '',
                'invoice_address' => $orderData['invoice_address'] ?? '',
                'invoice_city' => $orderData['invoice_city'] ?? '',
                'invoice_state' => $orderData['invoice_state'] ?? '',
                'invoice_postcode' => $orderData['invoice_postcode'] ?? '',
                'invoice_country_code' => $orderData['invoice_country_code'] ?? '',
                'want_invoice' => $orderData['want_invoice'] ?? false,
                'extra_field_1' => $orderData['extra_field_1'] ?? '',
                'extra_field_2' => $orderData['extra_field_2'] ?? '',
                'order_page' => $orderData['order_page'] ?? '',
                'pick_state' => $orderData['pick_state'] ?? 0,
                'pack_state' => $orderData['pack_state'] ?? 0,
                'delivery_country' => $orderData['delivery_country'] ?? '',
                'invoice_country' => $orderData['invoice_country'] ?? '',
                'products' => collect($orderData['products'] ?? []),
                'synced_at' => now(),
            ]
        );

        return $order;
    }
}