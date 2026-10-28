<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'sku',
        'name',
        'base_unit_id',
        'cost_price',
        'sell_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'integer',
            'sell_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function variantUnits(): HasMany
    {
        return $this->hasMany(VariantUnit::class, 'variant_id');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(VariantBarcode::class, 'variant_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'variant_id');
    }
}