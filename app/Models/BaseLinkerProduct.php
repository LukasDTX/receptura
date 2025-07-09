<?php
// app/Models/BaseLinkerProduct.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseLinkerProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'baselinker_product_id',
        'name',
        'sku',
        'ean',
        'price_brutto',
        'price_netto',
        'tax_rate',
        'stock',
        'weight',
        'description',
        'description_short',
        'image_url',
        'images',
        'category_id',
        'category_name',
        'is_active',
        'expiration_date',
        'manufacturer',
        'bundle_id',
        'avg_cost',
        'last_sync',
    ];

    protected $casts = [
        'baselinker_product_id' => 'integer',
        'price_brutto' => 'decimal:2',
        'price_netto' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock' => 'integer',
        'weight' => 'decimal:3',
        'is_active' => 'boolean',
        'expiration_date' => 'date',
        'images' => 'array',
        'avg_cost' => 'decimal:2',
        'last_sync' => 'datetime',
    ];

    /**
     * Relacja z lokalnym produktem
     */
    public function localProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'baselinker_product_id', 'baselinker_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query, $threshold = 5)
    {
        return $query->where('stock', '<=', $threshold);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock', '<=', 0);
    }

    public function scopeExpiring($query, $days = 30)
    {
        return $query->whereNotNull('expiration_date')
                    ->where('expiration_date', '<=', now()->addDays($days));
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiration_date')
                    ->where('expiration_date', '<', now());
    }

    /**
     * Accessors
     */
    public function getStockStatusAttribute(): string
    {
        return match (true) {
            $this->stock <= 0 => 'out_of_stock',
            $this->stock <= 5 => 'low_stock',
            default => 'in_stock',
        };
    }

    public function getExpirationStatusAttribute(): ?string
    {
        if (!$this->expiration_date) {
            return null;
        }

        if ($this->expiration_date->isPast()) {
            return 'expired';
        }

        if ($this->expiration_date->diffInDays() <= 30) {
            return 'expiring';
        }

        return 'valid';
    }

    /**
     * Statyczna metoda do tworzenia/aktualizacji z danych BaseLinker
     */
    public static function createOrUpdateFromBaseLinker(array $productData): self
    {
        return static::updateOrCreate(
            ['baselinker_product_id' => $productData['product_id']],
            [
                'name' => $productData['name'] ?? '',
                'sku' => $productData['sku'] ?? '',
                'ean' => $productData['ean'] ?? '',
                'price_brutto' => $productData['price_brutto'] ?? 0,
                'price_netto' => $productData['price_netto'] ?? 0,
                'tax_rate' => $productData['tax_rate'] ?? 23,
                'stock' => $productData['stock'] ?? 0,
                'weight' => $productData['weight'] ?? 0,
                'description' => $productData['description'] ?? '',
                'description_short' => $productData['description_short'] ?? '',
                'image_url' => $productData['images'][0] ?? null,
                'images' => $productData['images'] ?? [],
                'category_id' => $productData['category_id'] ?? null,
                'category_name' => $productData['category_name'] ?? null,
                'is_active' => $productData['is_active'] ?? true,
                'expiration_date' => isset($productData['expiration_date']) && $productData['expiration_date']
                    ? \Carbon\Carbon::parse($productData['expiration_date'])
                    : null,
                'manufacturer' => $productData['manufacturer'] ?? '',
                'bundle_id' => $productData['bundle_id'] ?? null,
                'avg_cost' => $productData['avg_cost'] ?? 0,
                'last_sync' => now(),
            ]
        );
    }
}