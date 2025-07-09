<?php

namespace App\Models;

use App\Enums\OkresWaznosci;
// use App\Traits\HasBaseLinkerSync;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produkt extends Model
{
    use HasFactory;
    // , HasBaseLinkerSync;

    protected $table = 'produkt';
    
    protected $fillable = [
        'nazwa',
        'kod',
        'receptura_id',
        'opakowanie_id',
        'opis',
        'koszt_calkowity',
        'cena_sprzedazy',
        'okres_waznosci',
        'meta',
        // Nowe pola BaseLinker
        'baselinker_id',
        'sync_with_baselinker',
        'baselinker_stock',
        'baselinker_price',
        'last_baselinker_sync',
        'stan_magazynowy',
        'ean',
        'waga',
        'zdjecie',
        'baselinker_data',
    ];
    
    protected $casts = [
        'meta' => 'array',
        'okres_waznosci' => OkresWaznosci::class,
        // Nowe casts
        'sync_with_baselinker' => 'boolean',
        'baselinker_stock' => 'integer',
        'baselinker_price' => 'decimal:2',
        'last_baselinker_sync' => 'datetime',
        'stan_magazynowy' => 'integer',
        'waga' => 'decimal:3',
        'baselinker_data' => 'array',
    ];

    protected $attributes = [
        'koszt_calkowity' => 0,
        'okres_waznosci' => '12M',
        'stan_magazynowy' => 0,
        'sync_with_baselinker' => false,
    ];

    public function receptura(): BelongsTo
    {
        return $this->belongsTo(Receptura::class);
    }

    public function opakowanie(): BelongsTo
    {
        return $this->belongsTo(Opakowanie::class);
    }

    public function obliczKosztCalkowity()
    {
        $this->receptura->obliczKosztCalkowity();
        $kosztCalkowity = $this->receptura->koszt_calkowity + $this->opakowanie->cena;
        
        $this->update(['koszt_calkowity' => $kosztCalkowity]);
        
        return $kosztCalkowity;
    }
    
    /**
     * Oblicza datę ważności na podstawie daty produkcji
     */
    public function obliczDataWaznosci(\Carbon\Carbon $dataProdukcji): \Carbon\Carbon
    {
        $miesiace = $this->okres_waznosci->getMonths();
        return $dataProdukcji->copy()->addMonths($miesiace);
    }

    /**
     * Scopes dla BaseLinker
     */
    public function scopeBaseLinkerSync($query)
    {
        return $query->where('sync_with_baselinker', true);
    }

    public function scopeWithBaseLinkerID($query)
    {
        return $query->whereNotNull('baselinker_id')->where('baselinker_id', '!=', '');
    }

    public function scopeWithoutBaseLinkerID($query)
    {
        return $query->where(function($q) {
            $q->whereNull('baselinker_id')->orWhere('baselinker_id', '');
        });
    }

    public function scopeLowStock($query, $threshold = 5)
    {
        return $query->where('stan_magazynowy', '<=', $threshold);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stan_magazynowy', '<=', 0);
    }

    /**
     * Accessors
     */
    public function getStockDifferenceAttribute(): ?int
    {
        if (!$this->baselinker_id || !is_numeric($this->baselinker_stock)) {
            return null;
        }
        
        return $this->stan_magazynowy - $this->baselinker_stock;
    }

    public function getStockStatusAttribute(): string
    {
        return match (true) {
            $this->stan_magazynowy <= 0 => 'out_of_stock',
            $this->stan_magazynowy <= 5 => 'low_stock',
            default => 'in_stock',
        };
    }

    /**
     * Przygotowuje dane produktu do wysyłki do BaseLinker
     */
    public function toBaseLinkerArray(): array
    {
        return [
            'catalog_id' => config('baselinker.default_catalog_id'),
            'baselinker_id' => $this->baselinker_id,
            'name' => $this->nazwa,
            'description' => $this->opis ?? '',
            'price' => $this->cena_sprzedazy,
            'tax_rate' => 23,
            'weight' => $this->waga ?? 0,
            'sku' => $this->kod ?? '',
            'ean' => $this->ean ?? '',
            'stock_data' => [
                'stock' => $this->stan_magazynowy ?? 0,
                'price' => $this->cena_sprzedazy,
                'inventory_id' => config('baselinker.default_inventory_id'),
                'warehouse_id' => config('baselinker.default_warehouse_id'),
            ]
        ];
    }

    /**
     * Sprawdza czy produkt wymaga synchronizacji
     */
    public function needsBaseLinkerSync(): bool
    {
        if (!$this->sync_with_baselinker) {
            return false;
        }

        return $this->baselinker_id === null || 
               $this->last_baselinker_sync === null ||
               $this->last_baselinker_sync < $this->updated_at;
    }
}