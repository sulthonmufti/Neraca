<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Outlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    protected $table = 'stock_levels';

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = [
        'outlet_id',
        'variant_id',
        'qty',
        'reorder_point',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'reorder_point' => 'decimal:3',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}